<?php
// api/react.php – Reaktion togglen via AJAX
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

$postId = (int) ($_POST['post_id'] ?? 0);
$type   = $_POST['type'] ?? '';

if ($postId <= 0 || !isset(reaction_types()[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Parameter']);
    exit;
}

$cu     = current_user();
$result = toggle_reaction($postId, (int) $cu['user_id'], $type);

if ($result === 'invalid') {
    http_response_code(400);
    echo json_encode(['error' => 'Reaktion fehlgeschlagen']);
    exit;
}

$reactions = post_reactions($postId, (int) $cu['user_id']);
$total     = array_sum(array_column($reactions, 'count'));

echo json_encode([
    'success'   => true,
    'action'    => $result,
    'reactions' => $reactions,
    'total'     => $total,
]);
