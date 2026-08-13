<?php
// api/bookmark.php
define('FORUM_RUNNING', true);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login erforderlich']);
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF']);
    exit;
}

$threadId = (int) ($_POST['thread_id'] ?? 0);
if ($threadId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiger Thread']);
    exit;
}

$cu     = current_user();
$result = toggle_bookmark((int) $cu['user_id'], $threadId);

if ($result === 'error') {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Speichern']);
    exit;
}

echo json_encode(['success' => true, 'action' => $result]);
