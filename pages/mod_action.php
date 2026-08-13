<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// mod_action.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

if (!verify_csrf($_GET['csrf'] ?? '')) die('CSRF-Fehler');

$action   = $_GET['action'] ?? '';
$threadId = get_int('thread');
$postId   = get_int('post');
$userId2  = get_int('user');

$cu = current_user();

/**
 * Prüft Berechtigung für das Board zu dem ein Thread oder Post gehört.
 * Aktionen auf User (ban etc.) sind global, brauchen require_mod().
 */
function ensure_board_permission(?int $threadId = null, ?int $postId = null): void {
    global $cu;
    $boardId = 0;
    if ($threadId) {
        $t = db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$threadId]);
        if ($t) $boardId = (int) $t['board_id'];
    } elseif ($postId) {
        $p = db_fetch('SELECT t.board_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id WHERE p.post_id = ?', [$postId]);
        if ($p) $boardId = (int) $p['board_id'];
    }
    if (!$boardId || !is_mod_for_board((int) $cu['user_id'], $boardId)) {
        http_response_code(403);
        die('Du darfst in diesem Forum nicht moderieren.');
    }
}

function mod_log_entry(string $act, string $type, int $id, string $reason = ''): void {
    global $cu;
    db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?)',
        [$cu['user_id'], $act, $type, $id, $reason]);
}

switch ($action) {
    case 'sticky':
        ensure_board_permission($threadId);
        db_execute('UPDATE threads SET is_sticky = 1 WHERE thread_id = ?', [$threadId]);
        mod_log_entry('sticky', 'thread', $threadId);
        flash('Thread angepinnt.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);

    case 'unsticky':
        ensure_board_permission($threadId);
        db_execute('UPDATE threads SET is_sticky = 0 WHERE thread_id = ?', [$threadId]);
        mod_log_entry('unsticky', 'thread', $threadId);
        flash('Thread losgelöst.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);

    case 'close':
        ensure_board_permission($threadId);
        db_execute('UPDATE threads SET is_closed = 1 WHERE thread_id = ?', [$threadId]);
        mod_log_entry('close', 'thread', $threadId);
        flash('Thread geschlossen.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);

    case 'open':
        ensure_board_permission($threadId);
        db_execute('UPDATE threads SET is_closed = 0 WHERE thread_id = ?', [$threadId]);
        mod_log_entry('open', 'thread', $threadId);
        flash('Thread geöffnet.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);

    case 'delete_thread':
        ensure_board_permission($threadId);
        $t = db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$threadId]);
        if ($t) {
            $rc = db_count('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [$threadId]);
            db_execute('DELETE FROM threads WHERE thread_id = ?', [$threadId]);
            db_execute('UPDATE boards SET thread_count = GREATEST(0, thread_count - 1),
                                          post_count   = GREATEST(0, post_count - ?)
                        WHERE board_id = ?', [$rc, $t['board_id']]);
            mod_log_entry('delete_thread', 'thread', $threadId);
            flash('Thread gelöscht.', 'success');
            redirect(FORUM_URL . '/board.php?id=' . $t['board_id']);
        }
        break;

    case 'delete_post':
        ensure_board_permission(null, $postId);
        $p = db_fetch('SELECT p.*, t.board_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id WHERE p.post_id = ?', [$postId]);
        if ($p) {
            db_execute('DELETE FROM posts WHERE post_id = ?', [$postId]);
            db_execute('UPDATE threads SET reply_count = GREATEST(0, reply_count - 1) WHERE thread_id = ?', [$p['thread_id']]);
            db_execute('UPDATE boards SET post_count = GREATEST(0, post_count - 1) WHERE board_id = ?', [$p['board_id']]);
            db_execute('UPDATE users SET post_count = GREATEST(0, post_count - 1) WHERE user_id = ?', [$p['user_id']]);
            mod_log_entry('delete_post', 'post', $postId);
            flash('Beitrag gelöscht.', 'success');
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }
        break;

    case 'ban':
        require_admin();
        db_execute('UPDATE users SET is_banned = 1 WHERE user_id = ?', [$userId2]);
        mod_log_entry('ban', 'user', $userId2);
        flash('Benutzer gesperrt.', 'success');
        redirect(FORUM_URL . '/profile.php?id=' . $userId2);

    case 'unban':
        require_admin();
        db_execute('UPDATE users SET is_banned = 0 WHERE user_id = ?', [$userId2]);
        mod_log_entry('unban', 'user', $userId2);
        flash('Benutzer entsperrt.', 'success');
        redirect(FORUM_URL . '/profile.php?id=' . $userId2);

    case 'make_mod':
        require_admin();
        db_execute('UPDATE users SET is_mod = 1 WHERE user_id = ?', [$userId2]);
        flash('Moderator ernannt.', 'success');
        redirect(FORUM_URL . '/admin/');

    case 'remove_mod':
        require_admin();
        db_execute('UPDATE users SET is_mod = 0 WHERE user_id = ?', [$userId2]);
        flash('Moderationsrechte entzogen.', 'success');
        redirect(FORUM_URL . '/admin/');

    default:
        die('Unbekannte Aktion.');
}
