<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// new_thread.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$boardId = get_int('board');
$board   = db_fetch('SELECT * FROM boards WHERE board_id = ?', [$boardId]);
if (!$board) { http_response_code(404); die('Forum nicht gefunden.'); }

// Passwort-Schutz: kein neuer Thread ohne Zugang
if (!board_user_can_access($board)) {
    redirect(FORUM_URL . '/board_unlock.php?id=' . $boardId);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF');

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

        DB::get()->beginTransaction();

        try {
            $threadId = db_execute(
                'INSERT INTO threads (board_id, user_id, title, is_approved) VALUES (?, ?, ?, ?)',
                [$boardId, $cu['user_id'], $title, $isApproved]
            );
        } catch (PDOException $e) {
            // Fallback falls is_approved Spalte fehlt (alte DB)
            $threadId = db_execute(
                'INSERT INTO threads (board_id, user_id, title) VALUES (?, ?, ?)',
                [$boardId, $cu['user_id'], $title]
            );
        }

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

        db_execute('UPDATE threads SET last_post_id = ? WHERE thread_id = ?', [$postId, $threadId]);

        // Counter nur erhöhen wenn approved
        if ($isApproved) {
            db_execute('UPDATE boards SET thread_count = thread_count + 1, post_count = post_count + 1, last_post_id = ? WHERE board_id = ?',
                [$postId, $boardId]);
            db_execute('UPDATE users SET post_count = post_count + 1, last_post_at = NOW(), last_activity_at = NOW() WHERE user_id = ?', [$cu['user_id']]);
            update_user_rank($cu['user_id']);
        }

        DB::get()->commit();

        // Anhänge
        try { process_attachments((int) $postId, (int) $cu['user_id']); } catch (Exception $e) {}

        // Erwähnungen nur senden wenn approved (sonst Spam-Vektor)
        if ($isApproved) {
            process_mentions($content, (int) $threadId, (int) $postId, (int) $cu['user_id']);
        }

        // Umfrage falls angegeben
        $pollQ = post_str('poll_question');
        $pollOpts = $_POST['poll_options'] ?? [];
        if (mb_strlen($pollQ) >= 3 && is_array($pollOpts) && count(array_filter($pollOpts, fn($o) => trim($o) !== '')) >= 2) {
            try { create_poll((int) $threadId, $pollQ, $pollOpts, isset($_POST['poll_multi'])); }
            catch (Exception $e) {}
        }

        // Tags
        if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
            set_thread_tags((int) $threadId, $_POST['tags']);
        }

        // Auto-Abo
        try {
            db_execute('INSERT IGNORE INTO thread_subscriptions (user_id, thread_id) VALUES (?, ?)',
                [$cu['user_id'], $threadId]);
        } catch (Exception $e) {}

        try { check_achievements((int) $cu['user_id']); } catch (Exception $e) {}
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
    'breadcrumb'  => [
        ['title' => $board['title'], 'url' => FORUM_URL . '/board.php?id=' . $boardId],
    ],
]);
