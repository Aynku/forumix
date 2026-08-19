<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// mod_action.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');

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
        error_page(403, 'Keine Berechtigung', 'Du bist in diesem Forum kein Moderator.');
    }
}

/**
 * Prüft Berechtigung für Aktionen auf einen Benutzer (sperren, Rechte
 * ändern). can_modify_user() schützt den Owner.
 */
function ensure_user_permission(int $targetId): void {
    global $cu;
    $target = $targetId > 0 ? db_fetch('SELECT * FROM users WHERE user_id = ?', [$targetId]) : null;
    if (!$target) {
        http_response_code(404);
        error_page(404, 'Benutzer nicht gefunden', 'Dieses Konto gibt es nicht.');
    }
    if (!can_modify_user($cu, $target)) {
        http_response_code(403);
        error_page(403, 'Nicht erlaubt', 'Dieser Benutzer darf nicht geändert werden.');
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

    case 'move':
        ensure_board_permission($threadId);
        $zielId = get_int('board');
        $t      = db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$threadId]);
        $ziel   = $zielId > 0 ? db_fetch('SELECT board_id FROM boards WHERE board_id = ?', [$zielId]) : null;
        if (!$t || !$ziel) {
            error_page(404, 'Forum nicht gefunden', 'Dieses Zielforum gibt es nicht.');
        }
        // Auch im Ziel muss moderiert werden duerfen. Sonst waere das
        // Verschieben ein Weg, ein Thema in ein fremdes Forum zu heben, in dem
        // man selbst nichts zu sagen hat.
        if (!is_mod_for_board((int) $cu['user_id'], $zielId)) {
            http_response_code(403);
            error_page(403, 'Keine Berechtigung', 'Im Zielforum bist du kein Moderator.');
        }
        if ((int) $t['board_id'] === $zielId) {
            flash('Das Thema liegt bereits in diesem Forum.', 'info');
            redirect(FORUM_URL . '/thread.php?id=' . $threadId);
        }
        db_execute('UPDATE threads SET board_id = ? WHERE thread_id = ?', [$zielId, $threadId]);
        board_zaehler_neu((int) $t['board_id']);
        board_zaehler_neu($zielId);
        mod_log_entry('move', 'thread', $threadId, 'nach Forum ' . $zielId);
        flash('Thema verschoben.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);

    /* Zu den GREATEST(CAST(... AS SIGNED) - 1, 0) weiter unten:
       Die Zaehlerspalten sind UNSIGNED. GREATEST(0, spalte - 1) schuetzt NICHT,
       weil die Subtraktion schon ueberlaeuft, bevor GREATEST drankommt —
       MariaDB bricht dann mit "BIGINT UNSIGNED value is out of range" ab. Beim
       Loeschen des einzigen Beitrags eines Themas (reply_count = 0) ist das
       jedes Mal passiert. Das CAST rechnet vorzeichenbehaftet, dann greift die
       Untergrenze wie gedacht. */
    case 'delete_thread':
        ensure_board_permission($threadId);
        $t = db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$threadId]);
        if ($t) {
            $rc = db_count('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [$threadId]);
            // Erst die Anhaenge, dann das Thema: nach dem DELETE gibt es die
            // Beitraege nicht mehr, und die Dateien waeren nicht mehr
            // zuzuordnen — sie blieben liegen und weiter abrufbar.
            delete_attachments_for_thread($threadId);
            // Beitragszaehler der Verfasser mitfuehren, sonst zeigt jedes
            // Profil dauerhaft zu viele Beitraege an.
            foreach (db_fetchAll('SELECT user_id, COUNT(*) AS n FROM posts WHERE thread_id = ? GROUP BY user_id',
                                 [$threadId]) as $v) {
                db_execute('UPDATE users SET post_count = GREATEST(CAST(post_count AS SIGNED) - ?, 0) WHERE user_id = ?',
                           [(int) $v['n'], (int) $v['user_id']]);
            }
            db_execute('DELETE FROM threads WHERE thread_id = ?', [$threadId]);
            db_execute('UPDATE boards SET thread_count = GREATEST(CAST(thread_count AS SIGNED) - 1, 0),
                                          post_count   = GREATEST(CAST(post_count AS SIGNED) - ?, 0)
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
            delete_attachments_for_posts([$postId]);
            db_execute('DELETE FROM posts WHERE post_id = ?', [$postId]);
            db_execute('UPDATE threads SET reply_count = GREATEST(CAST(reply_count AS SIGNED) - 1, 0) WHERE thread_id = ?', [$p['thread_id']]);
            db_execute('UPDATE boards SET post_count = GREATEST(CAST(post_count AS SIGNED) - 1, 0) WHERE board_id = ?', [$p['board_id']]);
            db_execute('UPDATE users SET post_count = GREATEST(CAST(post_count AS SIGNED) - 1, 0) WHERE user_id = ?', [$p['user_id']]);
            mod_log_entry('delete_post', 'post', $postId);
            flash('Beitrag gelöscht.', 'success');
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }
        break;

    case 'ban':
        require_admin();
        ensure_user_permission($userId2);
        db_execute('UPDATE users SET is_banned = 1 WHERE user_id = ?', [$userId2]);
        mod_log_entry('ban', 'user', $userId2);
        flash('Benutzer gesperrt.', 'success');
        redirect(FORUM_URL . '/profile.php?id=' . $userId2);

    case 'unban':
        require_admin();
        ensure_user_permission($userId2);
        db_execute('UPDATE users SET is_banned = 0 WHERE user_id = ?', [$userId2]);
        mod_log_entry('unban', 'user', $userId2);
        flash('Benutzer entsperrt.', 'success');
        redirect(FORUM_URL . '/profile.php?id=' . $userId2);

    case 'make_mod':
        require_admin();
        ensure_user_permission($userId2);
        db_execute('UPDATE users SET is_mod = 1 WHERE user_id = ?', [$userId2]);
        flash('Moderator ernannt.', 'success');
        redirect(FORUM_URL . '/admin/');

    case 'remove_mod':
        require_admin();
        ensure_user_permission($userId2);
        db_execute('UPDATE users SET is_mod = 0 WHERE user_id = ?', [$userId2]);
        flash('Moderationsrechte entzogen.', 'success');
        redirect(FORUM_URL . '/admin/');

    default:
        error_page(400, 'Unbekannte Aktion', 'Diese Moderations-Aktion gibt es nicht.');
}
