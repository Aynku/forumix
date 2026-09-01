<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// mark_all_read.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

$cu = current_user();
$n  = mark_all_threads_read((int) $cu['user_id']);
flash(tn('threads_marked_read', (int) $n), 'success');
redirect($_SERVER['HTTP_REFERER'] ?? FORUM_URL . '/index.php');
