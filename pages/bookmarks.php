<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// bookmarks.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu = current_user();
$bookmarks = user_bookmarks((int) $cu['user_id']);

render('bookmarks', [
    'title'     => 'Lesezeichen',
    'bookmarks' => $bookmarks,
]);
