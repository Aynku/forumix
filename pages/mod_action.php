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

    /* Loeschen legt in den Papierkorb, es entfernt nichts.
       Bis 1.2.x war jedes Loeschen endgueltig — ein Fehlgriff hier war nicht
       zu heilen. Jetzt bekommt der Beitrag einen Zeitstempel, verschwindet
       fuer alle ausser der Moderation, und die Zaehler gehen herunter. Was
       wirklich weg soll, geht den zweiten Weg: 'endgueltig_*'.

       Die Zaehlerarbeit steht in lib/papierkorb.php, weil Loeschen und
       Zurueckholen sonst zwangslaeufig auseinanderlaufen. Zum dortigen
       GREATEST(CAST(... AS SIGNED) - 1, 0): die Spalten sind UNSIGNED, und
       GREATEST(0, spalte - 1) schuetzt NICHT — die Subtraktion laeuft schon
       ueber, bevor GREATEST drankommt. */
    case 'delete_thread':
        ensure_board_permission($threadId);
        $t = db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$threadId]);
        if ($t) {
            if (thema_in_papierkorb($threadId, (int) $cu['user_id'])) {
                mod_log_entry('delete_thread', 'thread', $threadId, 'in den Papierkorb');
                flash('Thema liegt im Papierkorb. Wiederherstellen geht dort.', 'success');
            } else {
                flash('Das Thema liegt bereits im Papierkorb.', 'info');
            }
            redirect(FORUM_URL . '/board.php?id=' . $t['board_id']);
        }
        break;

    case 'restore_thread':
        ensure_board_permission($threadId);
        if (thema_zurueckholen($threadId)) {
            mod_log_entry('restore_thread', 'thread', $threadId);
            flash('Thema wiederhergestellt.', 'success');
        } else {
            flash('Dieses Thema liegt nicht im Papierkorb.', 'error');
        }
        redirect(FORUM_URL . '/thread.php?id=' . $threadId);

    case 'purge_thread':
        ensure_board_permission($threadId);
        $t = db_fetch('SELECT board_id, deleted_at FROM threads WHERE thread_id = ?', [$threadId]);
        if (!$t) break;
        if (empty($t['deleted_at'])) {
            flash('Erst in den Papierkorb, dann endgültig löschen.', 'error');
            redirect(FORUM_URL . '/thread.php?id=' . $threadId);
        }
        thema_endgueltig_loeschen($threadId);
        mod_log_entry('purge_thread', 'thread', $threadId, 'endgültig');
        flash('Thema endgültig gelöscht.', 'success');
        redirect(FORUM_URL . '/board.php?id=' . $t['board_id']);

    case 'delete_post':
        ensure_board_permission(null, $postId);
        $p = db_fetch('SELECT p.thread_id FROM posts p WHERE p.post_id = ?', [$postId]);
        if ($p) {
            // Der erste Beitrag traegt das Thema. Ihn allein wegzunehmen
            // hinterliesse ein Thema ohne Anfang — dafuer gibt es das Loeschen
            // des Themas.
            $erster = db_count('SELECT COALESCE(MIN(post_id), 0) FROM posts WHERE thread_id = ?', [$p['thread_id']]);
            if ($erster === $postId) {
                flash('Das ist der erste Beitrag — dafür das ganze Thema löschen.', 'error');
                redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
            }
            if (beitrag_in_papierkorb($postId, (int) $cu['user_id'])) {
                mod_log_entry('delete_post', 'post', $postId, 'in den Papierkorb');
                flash('Beitrag liegt im Papierkorb.', 'success');
            } else {
                flash('Der Beitrag liegt bereits im Papierkorb.', 'info');
            }
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }
        break;

    case 'restore_post':
        ensure_board_permission(null, $postId);
        $p = db_fetch('SELECT thread_id FROM posts WHERE post_id = ?', [$postId]);
        if ($p && beitrag_zurueckholen($postId)) {
            mod_log_entry('restore_post', 'post', $postId);
            flash('Beitrag wiederhergestellt.', 'success');
        } else {
            flash('Dieser Beitrag liegt nicht im Papierkorb.', 'error');
        }
        redirect(FORUM_URL . '/thread.php?id=' . ($p['thread_id'] ?? 0));

    case 'purge_post':
        ensure_board_permission(null, $postId);
        $p = db_fetch('SELECT thread_id, deleted_at FROM posts WHERE post_id = ?', [$postId]);
        if (!$p) break;
        if (empty($p['deleted_at'])) {
            flash('Erst in den Papierkorb, dann endgültig löschen.', 'error');
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }
        beitrag_endgueltig_loeschen($postId);
        mod_log_entry('purge_post', 'post', $postId, 'endgültig');
        flash('Beitrag endgültig gelöscht.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);

    /* Teilen: ab dem angegebenen Beitrag wandert alles Folgende in ein neues
       Thema. Der haeufigste Fall im Betrieb — ein Gespraech driftet ab einer
       Stelle ab. Einzelne Beitraege herauszupicken kann das bewusst nicht:
       das braucht Kaestchen an jedem Beitrag und mehr Stellen, an denen etwas
       schiefgehen kann. */
    case 'split':
        ensure_board_permission(null, $postId);
        $p = db_fetch('SELECT p.post_id, p.thread_id, p.user_id, p.created_at, t.board_id, t.title
                         FROM posts p JOIN threads t ON t.thread_id = p.thread_id
                        WHERE p.post_id = ?', [$postId]);
        if (!$p) break;

        $erster = db_count('SELECT COALESCE(MIN(post_id), 0) FROM posts WHERE thread_id = ?', [$p['thread_id']]);
        if ($erster === $postId) {
            flash('Am ersten Beitrag lässt sich nichts abtrennen — das wäre das ganze Thema.', 'error');
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }

        $titel = post_str('title') !== '' ? post_str('title') : 'Abgetrennt aus: ' . $p['title'];
        if (mb_strlen($titel) < 3) {
            flash('Der Titel des neuen Themas ist zu kurz.', 'error');
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }

        $neuesThema = thema_teilen($postId, $titel);
        if (!$neuesThema) {
            flash('Hier ließ sich nichts abtrennen.', 'error');
            redirect(FORUM_URL . '/thread.php?id=' . $p['thread_id']);
        }
        mod_log_entry('split', 'thread', (int) $p['thread_id'], 'ab Beitrag ' . $postId . ' → Thema ' . $neuesThema);
        flash('Thema geteilt.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $neuesThema);

    /* Zusammenfuehren: alles aus diesem Thema wandert in ein anderes, das
       leere bleibt nicht stehen. Moderiert werden muss auf beiden Seiten —
       sonst waere es ein Weg, Beitraege in ein fremdes Forum zu heben. */
    case 'merge':
        ensure_board_permission($threadId);
        $zielThema = post_int('ziel') ?: get_int('ziel');
        $quelle = db_fetch('SELECT thread_id, board_id FROM threads WHERE thread_id = ?', [$threadId]);
        $ziel   = $zielThema > 0 ? db_fetch('SELECT thread_id, board_id FROM threads WHERE thread_id = ?', [$zielThema]) : null;
        if (!$quelle || !$ziel) {
            error_page(404, 'Thema nicht gefunden', 'Dieses Zielthema gibt es nicht.');
        }
        if ((int) $quelle['thread_id'] === (int) $ziel['thread_id']) {
            flash('Ein Thema lässt sich nicht mit sich selbst zusammenführen.', 'error');
            redirect(FORUM_URL . '/thread.php?id=' . $threadId);
        }
        if (!is_mod_for_board((int) $cu['user_id'], (int) $ziel['board_id'])) {
            http_response_code(403);
            error_page(403, 'Keine Berechtigung', 'Im Forum des Zielthemas bist du kein Moderator.');
        }

        themen_zusammenfuehren($threadId, (int) $ziel['thread_id']);
        mod_log_entry('merge', 'thread', $threadId, 'in Thema ' . $ziel['thread_id']);
        flash('Themen zusammengeführt.', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $ziel['thread_id']);

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
