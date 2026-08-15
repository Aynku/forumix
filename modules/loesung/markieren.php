<?php
// modules/loesung/markieren.php – setzt oder loescht die Markierung
//
// Eigener Endpunkt, weil die Einhaengepunkte nur zeichnen. Wer schreibt,
// braucht Token, Rechtepruefung und eine Umleitung danach.
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/lib.php';

if (!module_enabled('loesung')) {
    error_page(404, 'Nicht gefunden', 'Das Modul ist nicht aktiv.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_page(405, 'Falscher Weg', 'Diese Adresse nimmt nur Formulare an.');
}
if (!verify_csrf($_POST['csrf'] ?? '')) {
    error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
}

$threadId = (int) ($_POST['thread_id'] ?? 0);
$postId   = (int) ($_POST['post_id'] ?? 0);

// Beitrag und Thema gemeinsam holen: so ist gleich geprueft, dass der Beitrag
// wirklich in diesem Thema steht. Sonst liesse sich ein fremder Beitrag als
// Loesung des eigenen Themas eintragen.
$row = db_fetch(
    'SELECT p.post_id, t.thread_id, t.user_id
     FROM posts p JOIN threads t ON t.thread_id = p.thread_id
     WHERE p.post_id = ? AND t.thread_id = ?',
    [$postId, $threadId]
);
if (!$row) {
    error_page(404, 'Nicht gefunden', 'Diesen Beitrag gibt es in diesem Thema nicht.');
}
if (!loesung_darf_markieren($row)) {
    error_page(403, 'Kein Zugriff', 'Das darf nur der Fragesteller oder die Moderation.');
}

try {
    if (($_POST['tun'] ?? '') === 'aufheben') {
        db_execute('DELETE FROM loesungen WHERE thread_id = ?', [$threadId]);
        flash('Markierung aufgehoben.', 'success');
    } else {
        // Ein Thema, eine Loesung: eine zweite Markierung ersetzt die erste.
        db_execute(
            'INSERT INTO loesungen (thread_id, post_id, gesetzt_von) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE post_id = VALUES(post_id), gesetzt_von = VALUES(gesetzt_von)',
            [$threadId, $postId, (int) current_user()['user_id']]
        );
        flash('Beitrag als Lösung markiert.', 'success');
    }
} catch (Exception $e) {
    error_log('loesung: ' . $e->getMessage());
    flash('Das hat nicht geklappt.', 'error');
}

redirect(FORUM_URL . '/thread.php?id=' . $threadId . '#post' . $postId);
