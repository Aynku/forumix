<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// subscribe.php
require_once __DIR__ . '/../bootstrap.php';
require_login();
if (!verify_csrf($_GET['csrf'] ?? '')) die('CSRF');

$threadId = (int) ($_GET['thread'] ?? 0);
$action   = $_GET['action'] ?? 'subscribe';
$cu = current_user();

try {
    if ($action === 'subscribe') {
        db_execute('INSERT IGNORE INTO thread_subscriptions (user_id, thread_id) VALUES (?, ?)',
            [$cu['user_id'], $threadId]);
        flash('Thread abonniert.', 'success');
    } else {
        db_execute('DELETE FROM thread_subscriptions WHERE user_id = ? AND thread_id = ?',
            [$cu['user_id'], $threadId]);
        flash('Abo entfernt.', 'info');
    }
} catch (Exception $e) {
    flash('Fehler: ' . $e->getMessage(), 'error');
}

redirect(FORUM_URL . '/thread.php?id=' . $threadId);
