<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// report.php – Beitrag melden
require_once __DIR__ . '/../bootstrap.php';
require_login();

$postId = get_int('post');
$post = db_fetch('SELECT p.*, t.thread_id, t.board_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id
                   WHERE p.post_id = ?' . sichtbar_sql('p') . sichtbar_sql('t'), [$postId]);
if (!$post) error_page(404, t('error_post_missing_title'), t('error_post_missing_text'));

// Die Seite zeigt eine Vorschau des Beitrags. Ohne Zugangspruefung waere sie
// ein Leseweg in passwortgeschuetzte Foren: post_id hochzaehlen genuegt.
if (!board_id_can_access((int) $post['board_id'])) {
    error_page(403, t('error_denied_title'), t('report_board_locked'));
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

    $reason  = post_str('reason');
    $comment = post_str('comment');
    $cu      = current_user();

    if (!isset(report_reasons()[$reason])) {
        $error = t('report_reason_required');
    } elseif (create_report($postId, (int) $cu['user_id'], $reason, $comment)) {
        $success = true;
        flash(t('report_sent'), 'success');
        redirect(FORUM_URL . '/thread.php?id=' . $post['thread_id'] . '#post' . $postId);
    } else {
        $error = t('report_already');
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
    'title'   => t('report_title'),
    'post'    => $post,
    'error'   => $error,
    'reasons' => $reasonOptions,
]);
