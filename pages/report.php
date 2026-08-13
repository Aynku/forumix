<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// report.php – Beitrag melden
require_once __DIR__ . '/../bootstrap.php';
require_login();

$postId = get_int('post');
$post = db_fetch('SELECT p.*, t.thread_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id WHERE p.post_id = ?', [$postId]);
if (!$post) { http_response_code(404); die('Beitrag nicht gefunden.'); }

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF');

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
