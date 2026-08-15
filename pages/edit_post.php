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
    error_page(404, 'Beitrag nicht gefunden', 'Diesen Beitrag gibt es nicht oder er wurde entfernt.');
}

$cu = current_user();
if ($cu['user_id'] !== $post['user_id'] && !is_mod()) {
    error_page(403, 'Keine Berechtigung', 'Bearbeiten darf nur der Autor selbst oder ein Moderator.');
}

$error = '';
$isModEdit = $cu['user_id'] != $post['user_id'];  // bearbeitet jemand fremden Post?

// Tags haengen am Thema, nicht am Beitrag. Bearbeitbar sind sie deshalb nur
// beim ersten Beitrag — sonst gaebe es fuenf Stellen, die dasselbe setzen.
// Ohne das liessen sich Tags ueberhaupt nur beim Erstellen vergeben, und ein
// Tag wie "Gelöst" waere sinnlos: den braucht man ja gerade hinterher.
$erstesPost   = (int) db_count('SELECT COALESCE(MIN(post_id), 0) FROM posts WHERE thread_id = ?',
                               [(int) $post['thread_id']]);
$tagsEditable = $erstesPost === (int) $postId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    $content    = post_str('content');
    $editReason = trim(post_str('edit_reason'));

    if (mb_strlen($content) < 3) {
        $error = 'Beitrag zu kurz.';
    } elseif ($isModEdit && mb_strlen($editReason) < 3) {
        // Mod-Edit erfordert Grund-Pflicht
        $error = '⚠️ Bei Bearbeitung eines fremden Beitrags ist ein Grund Pflicht.';
    } else {
        // Original-Inhalt im Edit-Log sichern
        record_post_edit((int) $postId, (int) $cu['user_id'], $post['content'], $content, $editReason);

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

        if ($tagsEditable) {
            // Kein Feld gesendet heisst "keine Tags" — sonst liessen sich
            // vergebene Tags nie wieder abwaehlen.
            set_thread_tags((int) $post['thread_id'],
                            is_array($_POST['tags'] ?? null) ? $_POST['tags'] : []);
        }

        redirect(FORUM_URL . '/thread.php?id=' . $post['thread_id'] . '#post' . $postId);
    }
}

render('edit_post', [
    'title'         => 'Beitrag bearbeiten',
    'post'          => $post,
    'error'         => $error,
    'isModEdit'     => $isModEdit,
    'tagsEditable'  => $tagsEditable,
    // Mit 'checked' angereichert: die Vorlagen-Engine kennt kein "in", der
    // Vergleich muss also hier passieren.
    'availableTags' => $tagsEditable ? (static function () use ($post) {
        $gesetzt = array_map('intval', array_column(thread_tags((int) $post['thread_id']), 'tag_id'));
        $alle    = all_tags();
        foreach ($alle as &$t) $t['checked'] = in_array((int) $t['tag_id'], $gesetzt, true);
        unset($t);
        return $alle;
    })() : [],
]);
