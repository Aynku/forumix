<?php
// api/set_theme.php – Theme-Wechsel persistent speichern
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$cu = current_user();
$theme = $_POST['theme'] ?? '';

if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
    echo json_encode(['ok' => false, 'error' => t('api_ungueltiges_theme')]);
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF']);
    exit;
}

if ($cu) {
    // Eingeloggter User: in DB speichern
    try {
        db_execute('UPDATE users SET theme = ? WHERE user_id = ?',
            [$theme, $cu['user_id']]);
        echo json_encode(['ok' => true, 'persisted' => 'db']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
} else {
    // Gast: Cookie setzen
    setcookie('forum_theme', $theme, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    echo json_encode(['ok' => true, 'persisted' => 'cookie']);
}
