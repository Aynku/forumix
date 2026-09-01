<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// post_history.php – Edit-Historie eines Beitrags (nur für Mods/Admins + Autor)
require_once __DIR__ . '/../bootstrap.php';
require_login();

$postId = get_int('post');
$post = db_fetch('
    SELECT p.*, t.title AS thread_title, t.thread_id, u.username AS author_name
    FROM posts p
    JOIN threads t ON t.thread_id = p.thread_id
    JOIN users u ON u.user_id = p.user_id
    WHERE p.post_id = ?
', [$postId]);
if (!$post) error_page(404, t('error_post_missing_title'), t('error_post_missing_text'));

$cu = current_user();
$canView = is_mod() || $cu['user_id'] == $post['user_id'];
if (!$canView) error_page(403, t('error_denied_title_short'), t('post_history_denied'));

$history = post_edit_history($postId);

render('post_history', [
    'title'   => t('post_history'),
    'post'    => $post,
    'history' => $history,
]);
