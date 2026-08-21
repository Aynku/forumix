<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// new_thread.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$boardId = get_int('board');
$board   = db_fetch('SELECT * FROM boards WHERE board_id = ?', [$boardId]);
if (!$board) error_page(404, 'Forum nicht gefunden', 'Dieses Forum gibt es nicht.');

// Passwort-Schutz: kein neuer Thread ohne Zugang
if (!board_user_can_access($board)) {
    if (board_zugangsgrund($board) === 'gruppe') {
        http_response_code(404);
        error_page(404, 'Forum nicht gefunden', 'Dieses Forum gibt es nicht.');
    }
    redirect(FORUM_URL . '/board_unlock.php?id=' . $boardId);
}

// Lesen reicht zum Mitlesen, nicht zum Schreiben. Seit 1.5.0 ist "Themen
// eroeffnen" ein eigenes Recht: in einem Ankuendigungsforum darf jeder
// antworten, aber nicht jeder ein Thema aufmachen.
if (!board_darf($boardId, 'thema')) {
    http_response_code(403);
    error_page(403, 'Nur zum Lesen', 'In diesem Forum darfst du keine neuen Themen anlegen.');
}

$darfAnhang  = board_darf($boardId, 'anhang');
$darfUmfrage = board_darf($boardId, 'umfrage');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');

    $title   = post_str('title');
    $content = post_str('content');

    $cu = current_user();

    if (mb_strlen($title) < 3) $error = 'Titel mindestens 3 Zeichen.';
    elseif (mb_strlen($content) < 3) $error = 'Beitragstext zu kurz.';

    // Cooldown
    if (!$error) {
        $cooldown = (int) get_setting('post_cooldown_seconds', 15);
        if ($cooldown > 0 && !is_mod()) {
            $lastPost = db_fetch(
                'SELECT created_at FROM posts WHERE user_id = ? ORDER BY post_id DESC LIMIT 1',
                [$cu['user_id']]
            );
            if ($lastPost && (time() - strtotime($lastPost['created_at'])) < $cooldown) {
                $diff = $cooldown - (time() - strtotime($lastPost['created_at']));
                $error = 'Bitte warte noch ' . $diff . ' Sekunden.';
            }
        }
    }

    if (!$error) {
        // Approval-Check: muss dieser Thread freigegeben werden?
        $needsApproval = false;
        if (!$cu['is_admin'] && !$cu['is_mod'] && empty($cu['is_owner'])) {
            $board = db_fetch('SELECT require_approval_threads FROM boards WHERE board_id = ?', [$boardId]);
            $needsApproval = !empty($board['require_approval_threads']);
        }
        $isApproved = $needsApproval ? 0 : 1;

        // Anlegen, Zaehlen, Benachrichtigen: lib/threads.php. Der Ablauf wird
        // auch von tools/versionsbeitrag.php gebraucht, und zweimal gepflegt
        // laeuft er zwangslaeufig auseinander.
        $neu = thread_erstellen($boardId, (int) $cu['user_id'], $title, $content, [
            'freigegeben' => (bool) $isApproved,
            'ip'          => client_ip(),
            // Anhaenge, Umfrage und Schlagworte haengen am Formular, nicht am
            // Thema. Sie laufen zwischen Commit und Erwaehnung, damit die
            // Benachrichtigung auf ein vollstaendiges Thema zeigt.
            'nachbereitung' => function (int $threadId, int $postId) use ($cu, $darfAnhang, $darfUmfrage) {
                if ($darfAnhang) {
                    try { process_attachments($postId, (int) $cu['user_id']); } catch (Exception $e) {}
                }

                $pollQ    = post_str('poll_question');
                $pollOpts = $_POST['poll_options'] ?? [];
                if ($darfUmfrage && mb_strlen($pollQ) >= 3 && is_array($pollOpts) && count(array_filter($pollOpts, fn($o) => trim($o) !== '')) >= 2) {
                    try { create_poll($threadId, $pollQ, $pollOpts, isset($_POST['poll_multi'])); }
                    catch (Exception $e) {}
                }

                if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                    set_thread_tags($threadId, $_POST['tags']);
                }
            },
        ]);
        $threadId = $neu['thread_id'];

        if ($isApproved) {
            flash('Thema erfolgreich erstellt!', 'success');
            redirect(FORUM_URL . '/thread.php?id=' . $threadId);
        } else {
            flash('⏳ Dein Thema wartet auf Freigabe durch einen Moderator. Du bekommst eine Benachrichtigung sobald es geprüft wurde.', 'info');
            redirect(FORUM_URL . '/board.php?id=' . $boardId);
        }
    }
}
render('new_thread', [
    'title'       => 'Neues Thema',
    'board'       => $board,
    'error'       => $error,
    'formTitle'   => post_str('title'),
    'formContent' => post_str('content'),
    'availableTags' => all_tags(),
    'darfAnhang'  => $darfAnhang,
    'darfUmfrage' => $darfUmfrage,
    'breadcrumb'  => [
        ['title' => $board['title'], 'url' => FORUM_URL . '/board.php?id=' . $boardId],
    ],
]);
