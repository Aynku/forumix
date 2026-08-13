<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// online.php
require_once __DIR__ . '/../bootstrap.php';

$users = online_users();
$stats = online_stats();

render('online', [
    'title' => '🟢 Wer ist online',
    'users' => $users,
    'stats' => $stats,
]);
