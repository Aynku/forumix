<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// edit_post.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$postId = get_int('id');
$post   = db_fetch('
    SELECT p.*, t.board_id
    FROM posts p JOIN threads t ON t.thread_id = p.thread_id
    WHERE p.post_id = ?
', [$postId]);

if (!$post) {
    http_response_code(404);
    die('Beitrag nicht gefunden.');
}

$cu = current_user();
if ($cu['user_id'] !== $post['user_id'] && !is_mod()) {
    http_response_code(403);
    die('Keine Berechtigung.');
}

$error = '';
$isModEdit = $cu['user_id'] != $post['user_id'];  // bearbeitet jemand fremden Post?

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');
    $content    = post_str('content');
    $editReason = trim(post_str('edit_reason'));

    if (mb_strlen($content) < 3) {
        $error = 'Beitrag zu kurz.';
    } elseif ($isModEdit && mb_strlen($editReason) < 3) {
        // Mod-Edit erfordert Grund-Pflicht
        $error = '⚠️ Bei Bearbeitung eines fremden Beitrags ist ein Grund Pflicht.';
    } else {
        // Original-Inhalt im Edit-Log sichern
        record_post_edit((int) $postId, (int) $cu['user_id'], $post['content'], $editReason);

        if ($isModEdit) {
            // Mod-Edit: speichern wer es war + Grund anzeigen
            try {
                db_execute(
                    'UPDATE posts SET content = ?, edited_at = NOW(), edited_by = ?,
                                       mod_edited_by = ?, mod_edit_reason = ?, mod_edited_at = NOW()
                     WHERE post_id = ?',
                    [$content, $cu['user_id'], $cu['user_id'], $editReason, $postId]
                );
            } catch (PDOException $e) {
                // Fallback ohne Mod-Edit-Spalten
                db_execute('UPDATE posts SET content = ?, edited_at = NOW(), edited_by = ? WHERE post_id = ?',
                    [$content, $cu['user_id'], $postId]);
            }

            // Den Autor benachrichtigen
            try {
                create_notification((int) $post['user_id'], 'mod_edit', [
                    'from_user_id' => (int) $cu['user_id'],
                    'thread_id'    => (int) $post['thread_id'],
                    'post_id'      => $postId,
                    'reason'       => $editReason,
                ]);
            } catch (Exception $e) {}

            flash('Beitrag bearbeitet und der Autor wurde benachrichtigt.', 'success');
        } else {
            // Eigener Edit – wie gehabt
            db_execute('UPDATE posts SET content = ?, edited_at = NOW(), edited_by = ? WHERE post_id = ?',
                [$content, $cu['user_id'], $postId]);
            flash('Beitrag gespeichert.', 'success');
        }

        redirect(FORUM_URL . '/thread.php?id=' . $post['thread_id'] . '#post' . $postId);
    }
}

render('edit_post', [
    'title'     => 'Beitrag bearbeiten',
    'post'      => $post,
    'error'     => $error,
    'isModEdit' => $isModEdit,
]);
