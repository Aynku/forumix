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

$cu = current_user();

// Darf der Benutzer den Beitrag ueberhaupt sehen? Die Schnittstelle nahm
// bisher jede post_id an — damit liessen sich Beitraege in
// passwortgeschuetzten Foren mit Reaktionen versehen und ueber die Antwort
// ihre Reaktionslisten auslesen, ohne das Passwort je eingegeben zu haben.
// Ausserdem hebelte sie geschlossene Themen und den Wartungsmodus aus, weil
// die Pruefungen nur in den Seiten stehen, nicht hier.
$brett = db_fetch(
    'SELECT b.board_id, b.password_hash, t.is_closed
       FROM posts p
       JOIN threads t ON t.thread_id = p.thread_id
       JOIN boards  b ON b.board_id  = t.board_id
      WHERE p.post_id = ?',
    [$postId]
);
if (!$brett) {
    http_response_code(404);
    echo json_encode(['error' => 'Beitrag nicht gefunden']);
    exit;
}
if (!board_user_can_access($brett)) {
    http_response_code(403);
    echo json_encode(['error' => 'Kein Zugriff auf dieses Forum']);
    exit;
}
if (!empty($brett['is_closed']) && !board_has_privileged_access((int) $brett['board_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Thema ist geschlossen']);
    exit;
}

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
