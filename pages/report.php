<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// report.php – Beitrag melden
require_once __DIR__ . '/../bootstrap.php';
require_login();

$postId = get_int('post');
$post = db_fetch('SELECT p.*, t.thread_id, t.board_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id WHERE p.post_id = ?', [$postId]);
if (!$post) error_page(404, 'Beitrag nicht gefunden', 'Dieser Beitrag gibt es nicht oder er wurde entfernt.');

// Die Seite zeigt eine Vorschau des Beitrags. Ohne Zugangspruefung waere sie
// ein Leseweg in passwortgeschuetzte Foren: post_id hochzaehlen genuegt.
if (!board_id_can_access((int) $post['board_id'])) {
    error_page(403, 'Kein Zugriff', 'Dieser Beitrag liegt in einem passwortgeschützten Forum.');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');

    $reason  = post_str('reason');
    $comment = post_str('comment');
    $cu      = current_user();

    if (!isset(report_reasons()[$reason])) {
        $error = 'Bitte einen Grund auswählen.';
    } elseif (create_report($postId, (int) $cu['user_id'], $reason, $comment)) {
        $success = true;
        flash('Meldung erfolgreich abgesendet. Vielen Dank!', 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $post['thread_id'] . '#post' . $postId);
    } else {
        $error = 'Du hast diesen Beitrag bereits gemeldet.';
    }
}

// Für das Template: assoziatives [key => label] in eine iterierbare Liste
// umformen, da die Template-Engine kein {% for k, v in ... %} kann.
// report_reasons() selbst bleibt unangetastet (Validierung oben nutzt die Keys).
$reasonOptions = [];
foreach (report_reasons() as $key => $label) {
    $reasonOptions[] = ['key' => $key, 'label' => $label];
}

render('report', [
    'title'   => 'Beitrag melden',
    'post'    => $post,
    'error'   => $error,
    'reasons' => $reasonOptions,
]);
