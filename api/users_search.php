<?php
// api/users_search.php – Autocomplete für @Mentions
define('FORUM_RUNNING', true);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Nur eingeloggte User dürfen Vorschläge bekommen (Privacy)
$cu = current_user();
if (!$cu) {
    http_response_code(401);
    echo json_encode(['users' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['users' => []]);
    exit;
}

// Begrenzte Suche – nur die ersten 8 Treffer
try {
    $users = db_fetchAll('
        SELECT user_id, username, avatar, is_admin, is_mod,
               COALESCE(is_owner, 0) AS is_owner
        FROM users
        WHERE username LIKE ?
          AND is_banned = 0
        ORDER BY
          CASE WHEN username = ? THEN 0 ELSE 1 END,
          CASE WHEN username LIKE ? THEN 0 ELSE 1 END,
          post_count DESC
        LIMIT 8
    ', [$q . '%', $q, $q . '%']);
} catch (Exception $e) {
    $users = [];
}

$result = [];
foreach ($users as $u) {
    $result[] = [
        'id'       => (int) $u['user_id'],
        'name'     => $u['username'],
        'avatar'   => avatar_url($u),
        'is_owner' => (bool) $u['is_owner'],
        'is_admin' => (bool) $u['is_admin'],
        'is_mod'   => (bool) $u['is_mod'],
    ];
}

echo json_encode(['users' => $result]);
