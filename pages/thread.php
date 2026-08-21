<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// thread.php
require_once __DIR__ . '/../bootstrap.php';

$threadId = get_int('id');
$thread = db_fetch('
    SELECT t.*, b.title AS board_title, b.board_id, c.title AS cat_title, u.username AS author
    FROM threads t
    JOIN boards b     ON b.board_id = t.board_id
    JOIN categories c ON c.cat_id   = b.cat_id
    JOIN users u      ON u.user_id  = t.user_id
    WHERE t.thread_id = ?
', [$threadId]);

if (!$thread) error_page(404, 'Thread nicht gefunden', 'Dieses Thema gibt es nicht oder es wurde entfernt.');

// Noch nicht freigegeben? Dann sehen es nur der Verfasser und die Moderation.
// Ueber die ID war es bisher fuer jeden aufrufbar — die Freigabe-Warteschlange
// haelt gerade das zurueck, was noch niemand sehen soll.
if (isset($thread['is_approved']) && (int) $thread['is_approved'] === 0) {
    $cu = current_user();
    $darf = $cu && ((int) $cu['user_id'] === (int) $thread['user_id']
                    || board_has_privileged_access((int) $thread['board_id']));
    if (!$darf) {
        error_page(404, 'Thread nicht gefunden', 'Dieses Thema gibt es nicht oder es wurde entfernt.');
    }
}

// Passwort-Schutz: wenn das Board geschützt ist und User keinen Zugang hat → zur Unlock-Seite
$threadBoard = db_fetch('SELECT board_id, password_hash FROM boards WHERE board_id = ?', [$thread['board_id']]);
if ($threadBoard && !board_user_can_access($threadBoard)) {
    // Wie im Forum selbst: fehlende Gruppe sieht aus wie "gibt es nicht".
    if (board_zugangsgrund($threadBoard) === 'gruppe') {
        http_response_code(404);
        error_page(404, 'Thread nicht gefunden', 'Dieses Thema gibt es nicht oder es wurde entfernt.');
    }
    redirect(FORUM_URL . '/board_unlock.php?id=' . $thread['board_id']);
}

db_execute('UPDATE threads SET view_count = view_count + 1 WHERE thread_id = ?', [$threadId]);

// Thread als gelesen markieren (für eingeloggte User)
if (current_user()) {
    mark_thread_read((int) current_user()['user_id'], $threadId);
}

// ─── Vote-Handler ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['poll_vote'])) {
    require_login();
    if (!verify_csrf(post_str('csrf'))) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    $pollId = (int) post_int('poll_id');
    $opts   = $_POST['option'] ?? [];
    if (!is_array($opts)) $opts = [$opts];
    $cu = current_user();
    if (cast_vote($pollId, $opts, (int) $cu['user_id'], $threadId)) {
        flash('Stimme abgegeben!', 'success');
    } else {
        flash('Abstimmung fehlgeschlagen.', 'error');
    }
    redirect(FORUM_URL . '/thread.php?id=' . $threadId);
}

// ─── Antwort speichern ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    require_login();
    if (!verify_csrf(post_str('csrf'))) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');

    if ($thread['is_closed'] && !is_mod()) {
        flash('Dieser Thread ist geschlossen.', 'error');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);
    }

    // In ein Thema im Papierkorb wird nicht geschrieben — auch nicht von der
    // Moderation, die es ja noch sieht. Sonst haengt nach dem Zurueckholen
    // eine Antwort daran, die es waehrend der Loeschung nicht geben konnte.
    if (!empty($thread['deleted_at'])) {
        flash('Dieses Thema liegt im Papierkorb.', 'error');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);
    }

    // Lesen reicht nicht zum Antworten. Der Knopf wird zwar gar nicht erst
    // angezeigt, aber wer die Anfrage von Hand schickt, kommt hier an.
    if (!board_darf((int) $thread['board_id'], 'antwort')) {
        http_response_code(403);
        error_page(403, 'Nur zum Lesen', 'In diesem Forum darfst du nicht antworten.');
    }

    $content = post_str('content');
    if (mb_strlen($content) < 3) {
        flash('Beitrag zu kurz.', 'error');
    } else {
        $cu = current_user();

        // Spam-Schutz: Cooldown zwischen Posts
        $cooldown = (int) get_setting('post_cooldown_seconds', 15);
        if ($cooldown > 0 && !is_mod()) {
            $lastPost = db_fetch(
                'SELECT created_at FROM posts WHERE user_id = ? ORDER BY post_id DESC LIMIT 1',
                [$cu['user_id']]
            );
            if ($lastPost) {
                $diff = time() - strtotime($lastPost['created_at']);
                if ($diff < $cooldown) {
                    flash('Bitte warte noch ' . ($cooldown - $diff) . ' Sekunden vor dem nächsten Beitrag.', 'error');
                    redirect(FORUM_URL . '/thread.php?id=' . $threadId);
                }
            }
        }

        // Approval-Check
        $needsApproval = false;
        if (!$cu['is_admin'] && !$cu['is_mod'] && empty($cu['is_owner'])) {
            $board = db_fetch('SELECT require_approval_replies FROM boards WHERE board_id = ?', [$thread['board_id']]);
            $needsApproval = !empty($board['require_approval_replies']);
        }
        $isApproved = $needsApproval ? 0 : 1;

        try {
            $postId = db_execute(
                'INSERT INTO posts (thread_id, user_id, content, author_ip, is_approved) VALUES (?, ?, ?, ?, ?)',
                [$threadId, $cu['user_id'], $content, client_ip(), $isApproved]
            );
        } catch (PDOException $e) {
            $postId = db_execute(
                'INSERT INTO posts (thread_id, user_id, content) VALUES (?, ?, ?)',
                [$threadId, $cu['user_id'], $content]
            );
        }
        try { log_user_ip($cu['user_id'], 'post'); } catch (Exception $e) {}

        // Counter nur erhöhen wenn approved
        if ($isApproved) {
            db_execute('UPDATE threads SET reply_count = reply_count + 1, last_post_id = ? WHERE thread_id = ?', [$postId, $threadId]);
            db_execute('UPDATE boards SET post_count = post_count + 1, last_post_id = ? WHERE board_id = ?', [$postId, $thread['board_id']]);
            db_execute('UPDATE users SET post_count = post_count + 1, last_post_at = NOW(), last_activity_at = NOW() WHERE user_id = ?', [$cu['user_id']]);
            update_user_rank($cu['user_id']);
        }

        // Anhänge verarbeiten — sofern die Gruppe hier anhängen darf. Das Feld
        // wird sonst gar nicht gezeichnet; wer es von Hand schickt, laeuft
        // hier ins Leere statt in eine Fehlermeldung.
        if (board_darf((int) $thread['board_id'], 'anhang')) {
            try { process_attachments($postId, (int) $cu['user_id']); } catch (Exception $e) {}
        }

        // Erwähnungen + Subscriber nur wenn approved
        if ($isApproved) {
            process_mentions($content, $threadId, (int) $postId, (int) $cu['user_id']);
            notify_thread_subscribers($threadId, (int) $postId, (int) $cu['user_id']);
        }

        // Automatisch selbst abonnieren falls Notifications aktiv
        try {
            db_execute('INSERT IGNORE INTO thread_subscriptions (user_id, thread_id) VALUES (?, ?)',
                [$cu['user_id'], $threadId]);
        } catch (Exception $e) {}

        try { check_achievements((int) $cu['user_id']); } catch (Exception $e) {}
        if ($isApproved) {
            flash('Beitrag erfolgreich erstellt.', 'success');
            redirect(FORUM_URL . '/thread.php?id=' . $threadId . '&page=last#post' . $postId);
        } else {
            flash('⏳ Dein Beitrag wartet auf Freigabe durch einen Moderator.', 'info');
            redirect(FORUM_URL . '/thread.php?id=' . $threadId);
        }
    }
}

// Approval-Filter: User sehen ihre eigenen unapproved Posts, Mods/Admins sehen alle
$currentUser   = current_user();
$currentUserId = $currentUser ? (int) $currentUser['user_id'] : 0;
$isAdmin       = $currentUser && (!empty($currentUser['is_admin']) || !empty($currentUser['is_owner']));
$isMod         = $currentUser && !empty($currentUser['is_mod']);
$canSeeAll     = $isAdmin || $isMod ||
                 ($currentUser && is_mod_for_board($currentUserId, (int) $thread['board_id']));

if ($canSeeAll) {
    $approvalFilter = '';
    $approvalParams = [];
} else {
    $approvalFilter = ' AND (COALESCE(p.is_approved, 1) = 1 OR p.user_id = ?)';
    $approvalParams = [$currentUserId];
}

// Papierkorb: Geloeschtes sieht nur, wer moderieren darf — und zwar an Ort und
// Stelle, damit beim Zurueckholen erkennbar bleibt, worauf sich die Antworten
// danach beziehen. Fuer alle anderen ist es weg, auch fuer den Verfasser.
$approvalFilter .= $canSeeAll ? '' : sichtbar_sql('p');

// Und das Thema selbst: liegt es im Papierkorb, ist es fuer alle uebrigen
// dasselbe wie ein Thema, das es nicht gibt.
if (!empty($thread['deleted_at']) && !$canSeeAll) {
    error_page(404, 'Thread nicht gefunden', 'Dieses Thema gibt es nicht oder es wurde entfernt.');
}

$page  = get_int('page', 1);
$total = db_count(
    'SELECT COUNT(*) FROM posts p WHERE p.thread_id = ?' . $approvalFilter,
    array_merge([$threadId], $approvalParams)
);
$pag   = paginate($total, POSTS_PER_PAGE, $page);

if (isset($_GET['page']) && $_GET['page'] === 'last') {
    $pag = paginate($total, POSTS_PER_PAGE, $pag['total_pages']);
}

$posts = db_fetchAll(
    'SELECT p.*, u.username, u.avatar, u.signature, u.post_count, u.rank,
            u.registered_at, u.is_mod, u.is_admin, u.is_banned,
            COALESCE(u.is_owner, 0)   AS is_owner,
            COALESCE(u.is_deleted, 0) AS is_deleted,
            COALESCE(p.is_approved, 1) AS is_approved,
            mu.username AS mod_editor_name,
            du.username AS geloescht_von
     FROM posts p
     JOIN users u ON u.user_id = p.user_id
     LEFT JOIN users mu ON mu.user_id = p.mod_edited_by
     LEFT JOIN users du ON du.user_id = p.deleted_by
     WHERE p.thread_id = ?' . $approvalFilter . '
     ORDER BY p.created_at ASC
     LIMIT ? OFFSET ?',
    array_merge([$threadId], $approvalParams, [$pag['per_page'], $pag['offset']])
);

// Gruppen-Abzeichen der Verfasser. Eine Abfrage fuer alle Beitraege der Seite
// statt einer je Beitrag — sonst waeren es bei zwanzig Beitraegen zwanzig.
$abzeichen = [];
$ohneRang  = [];   // Konten, deren Gruppe den Rang ersetzt
$verfasser = array_values(array_unique(array_map(fn($p) => (int) $p['user_id'], $posts)));
if ($verfasser) {
    $platzhalter = implode(',', array_fill(0, count($verfasser), '?'));
    // hide_rank gibt es erst ab v31. Fehlt die Spalte, wird die Abfrage ohne
    // sie wiederholt — sonst stuenden nach einem halben Update gar keine
    // Abzeichen mehr da.
    $sql = 'SELECT m.user_id, g.name, %s AS versteckt FROM group_members m
            JOIN user_groups g ON g.group_id = m.group_id
            WHERE g.show_badge = 1 AND m.user_id IN (' . $platzhalter . ')
            ORDER BY g.position, g.name';
    foreach (['g.hide_rank', '0'] as $spalte) {
        try {
            foreach (db_fetchAll(sprintf($sql, $spalte), $verfasser) as $r) {
                $abzeichen[(int) $r['user_id']][] = $r['name'];
                if (!empty($r['versteckt'])) $ohneRang[(int) $r['user_id']] = true;
            }
            break;
        } catch (Exception $e) {
            // Erster Versuch mit der Spalte gescheitert: ohne sie nochmal.
        }
    }
}

// Anhänge + Reaktionen pro Post laden
$reactionTypes = reaction_types();
foreach ($posts as &$p) {
    $p['gruppen']     = $abzeichen[(int) $p['user_id']] ?? [];
    $p['ohne_rang']   = isset($ohneRang[(int) $p['user_id']]);
    $p['attachments'] = post_attachments((int) $p['post_id']);
    $p['reactions']   = post_reactions((int) $p['post_id'], current_user() ? (int) current_user()['user_id'] : null);

    // Reaktionen mit Emoji + Label anreichern und unbekannte Typen verwerfen.
    // Die Template-Engine kann kein continue und keinen dynamischen
    // reaction_types()[$type]-Zugriff – daher hier aufbereiten.
    $enrichedReactions = [];
    foreach ($p['reactions'] as $r) {
        $info = $reactionTypes[$r['type']] ?? null;
        if (!$info) continue;
        $r['emoji'] = $info[0];
        $r['label'] = $info[1];
        $enrichedReactions[] = $r;
    }
    $p['reactions'] = $enrichedReactions;

    // Modul-Haken unter dem Beitrag. Die neue Vorlagen-Engine kann keine
    // void-Funktion einbinden, deshalb hier puffern und als fertigen String
    // mitgeben — dasselbe Vorgehen wie bei 'home_top' auf der Startseite.
    ob_start();
    render_module_hook('post_footer', ['post' => $p, 'thread' => $thread]);
    $p['hookFooter'] = ob_get_clean();
}
unset($p);

// Umfrage zum Thread (falls vorhanden)
$poll = get_poll_for_thread($threadId);
$hasVoted = false;
if ($poll && current_user()) {
    $hasVoted = user_has_voted((int) $poll['poll_id'], (int) current_user()['user_id']);
}

// Abo-Status
$isSubscribed = false;
$isBookmarked = false;
if (current_user()) {
    try {
        $isSubscribed = db_count(
            'SELECT COUNT(*) FROM thread_subscriptions WHERE user_id = ? AND thread_id = ?',
            [current_user()['user_id'], $threadId]
        ) > 0;
    } catch (Exception $e) {}
    $isBookmarked = is_bookmarked((int) current_user()['user_id'], $threadId);
}

// Tags zum Thread laden
$threadTags = thread_tags($threadId);

// Haken oberhalb des ersten Beitrags, gleiches Vorgehen wie unten am Beitrag.
ob_start();
render_module_hook('thread_top', ['thread' => $thread]);
$threadTopHtml = ob_get_clean();

// Zielforen fuers Verschieben. Nur fuer Moderatoren des aktuellen Forums
// geladen — sonst waere es eine Liste, die niemand sieht. Das eigene Forum
// faellt raus, dorthin verschiebt man nicht.
$moveBoards = [];
$cuThread   = current_user();
if ($cuThread && (is_admin() || is_mod_for_board((int) $cuThread['user_id'], (int) $thread['board_id']))) {
    $moveBoards = db_fetchAll('
        SELECT b.board_id, b.title, b.password_hash, c.title AS cat
        FROM boards b JOIN categories c ON c.cat_id = b.cat_id
        ORDER BY c.position, b.position
    ');
    $moveBoards = array_values(array_filter(
        $moveBoards,
        fn($b) => (int) $b['board_id'] !== (int) $thread['board_id'] && board_user_can_access($b)
    ));
}

$darfSchreiben = board_darf((int) $thread['board_id'], 'antwort');
$darfAnhang    = board_darf((int) $thread['board_id'], 'anhang');
$darfBearbeiten = board_darf((int) $thread['board_id'], 'bearbeiten');

render('thread', [
    'darfSchreiben' => $darfSchreiben,
    'darfAnhang'    => $darfAnhang,
    'darfBearbeiten' => $darfBearbeiten,
    'title'         => $thread['title'],
    'threadTopHtml' => $threadTopHtml,
    'thread'        => $thread,
    'threadTags'    => $threadTags,
    'moveBoards'    => $moveBoards,
    'posts'        => $posts,
    'pag'          => $pag,
    'pagination'   => pagination_html($pag, FORUM_URL . '/thread.php?id=' . $threadId),
    'poll'         => $poll,
    'hasVoted'     => $hasVoted,
    'isSubscribed' => $isSubscribed,
    'isBookmarked' => $isBookmarked,
    'breadcrumb'   => [
        ['title' => $thread['cat_title'],   'url' => FORUM_URL . '/index.php'],
        ['title' => $thread['board_title'], 'url' => FORUM_URL . '/board.php?id=' . $thread['board_id']],
        ['title' => $thread['title'],       'url' => FORUM_URL . '/thread.php?id=' . $threadId],
    ],
]);
