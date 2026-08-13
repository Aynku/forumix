<?php
// api/preview.php – BBCode + Smileys live rendern
define('FORUM_RUNNING', true);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login']);
    exit;
}

$content = $_POST['content'] ?? '';
if (mb_strlen($content) > 50000) {
    $content = mb_substr($content, 0, 50000);
}

echo json_encode(['html' => bbcode($content)]);
