<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// mark_all_read.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');

$cu = current_user();
$n  = mark_all_threads_read((int) $cu['user_id']);
flash($n . ' Threads als gelesen markiert.', 'success');
redirect($_SERVER['HTTP_REFERER'] ?? FORUM_URL . '/index.php');
