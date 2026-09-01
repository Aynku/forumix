<?php
// modules/loesung/markieren.php – setzt oder loescht die Markierung
//
// Eigener Endpunkt, weil die Einhaengepunkte nur zeichnen. Wer schreibt,
// braucht Token, Rechtepruefung und eine Umleitung danach.
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/lib.php';

if (!module_enabled('loesung')) {
    error_page(404, t('error_not_found_title'), t('loesung_disabled'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_page(405, t('error_wrong_method_title'), t('error_wrong_method_text'));
}
if (!verify_csrf($_POST['csrf'] ?? '')) {
    error_page(400, t('error_csrf_title'), t('error_csrf_text'));
}

$threadId = (int) ($_POST['thread_id'] ?? 0);
$postId   = (int) ($_POST['post_id'] ?? 0);

// Beitrag und Thema gemeinsam holen: so ist gleich geprueft, dass der Beitrag
// wirklich in diesem Thema steht. Sonst liesse sich ein fremder Beitrag als
// Loesung des eigenen Themas eintragen.
$row = db_fetch(
    'SELECT p.post_id, t.thread_id, t.user_id
     FROM posts p JOIN threads t ON t.thread_id = p.thread_id AND t.deleted_at IS NULL
     WHERE p.post_id = ? AND t.thread_id = ?',
    [$postId, $threadId]
);
if (!$row) {
    error_page(404, t('error_not_found_title'), t('loesung_post_missing'));
}
if (!loesung_darf_markieren($row)) {
    error_page(403, t('error_denied_title'), t('loesung_denied'));
}

try {
    if (($_POST['tun'] ?? '') === 'aufheben') {
        db_execute('DELETE FROM loesungen WHERE thread_id = ?', [$threadId]);
        flash(t('loesung_unmarked'), 'success');
    } else {
        // Ein Thema, eine Loesung: eine zweite Markierung ersetzt die erste.
        db_execute(
            'INSERT INTO loesungen (thread_id, post_id, gesetzt_von) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE post_id = VALUES(post_id), gesetzt_von = VALUES(gesetzt_von)',
            [$threadId, $postId, (int) current_user()['user_id']]
        );
        flash(t('loesung_marked'), 'success');
    }
} catch (Exception $e) {
    error_log('loesung: ' . $e->getMessage());
    flash(t('loesung_failed'), 'error');
}

redirect(FORUM_URL . '/thread.php?id=' . $threadId . '#post' . $postId);
