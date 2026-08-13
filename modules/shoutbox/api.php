<?php
// modules/shoutbox/api.php – AJAX-Endpunkte für die Shoutbox
// Wird direkt vom Browser aufgerufen (POST/GET), nicht included.

if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

if (!module_enabled('shoutbox')) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['ok' => false, 'error' => 'Modul nicht aktiv']));
}

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['shout_action'] ?? 'list';
$cu     = current_user();

// ── LISTE LADEN ────────────────────────────────────────────────
if ($action === 'list') {
    try {
        $sinceId = (int) ($_GET['since'] ?? 0);
        $params = [];
        $where = '';
        if ($sinceId > 0) {
            $where = ' WHERE s.shout_id > ?';
            $params[] = $sinceId;
        }
        $rows = db_fetchAll('
            SELECT s.shout_id, s.message, s.created_at,
                   u.user_id, u.username, u.avatar,
                   COALESCE(u.is_admin, 0) AS is_admin,
                   COALESCE(u.is_mod, 0)   AS is_mod,
                   COALESCE(u.is_owner, 0) AS is_owner
            FROM shoutbox_messages s
            JOIN users u ON u.user_id = s.user_id
            ' . $where . '
            ORDER BY s.shout_id DESC
            LIMIT 30
        ', $params);
        // In aufsteigender Reihenfolge zurückgeben für die Anzeige
        $rows = array_reverse($rows);
        echo json_encode(['ok' => true, 'messages' => $rows]);
    } catch (Exception $e) {
        error_log('[shoutbox] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Serverfehler']);
    }
    exit;
}

// Ab hier Login nötig
if (!$cu) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

// ── NEU POSTEN ────────────────────────────────────────────────
if ($action === 'post') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF']);
        exit;
    }
    $msg = trim($_POST['message'] ?? '');
    if (mb_strlen($msg) < 1) {
        echo json_encode(['ok' => false, 'error' => 'Bitte etwas eingeben']);
        exit;
    }
    if (mb_strlen($msg) > 500) {
        $msg = mb_substr($msg, 0, 500);
    }

    // Rate-Limit: max 1 Nachricht pro 5 Sekunden
    try {
        $last = db_fetch('SELECT created_at FROM shoutbox_messages WHERE user_id = ? ORDER BY shout_id DESC LIMIT 1',
            [$cu['user_id']]);
        if ($last) {
            $diff = time() - strtotime($last['created_at']);
            if ($diff < 5) {
                echo json_encode(['ok' => false, 'error' => 'Bitte ' . (5 - $diff) . 's warten…']);
                exit;
            }
        }
    } catch (Exception $e) {}

    try {
        db_execute('INSERT INTO shoutbox_messages (user_id, message) VALUES (?, ?)',
            [$cu['user_id'], $msg]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log('[shoutbox] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Serverfehler']);
    }
    exit;
}

// ── NACHRICHT LÖSCHEN (eigene oder als Mod/Admin) ────────────
if ($action === 'delete') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF']);
        exit;
    }
    $id = (int) ($_POST['shout_id'] ?? 0);
    try {
        $row = db_fetch('SELECT user_id FROM shoutbox_messages WHERE shout_id = ?', [$id]);
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Nicht gefunden']);
            exit;
        }
        $isOwn = (int) $row['user_id'] === (int) $cu['user_id'];
        $canMod = $cu['is_admin'] || $cu['is_mod'] || !empty($cu['is_owner']);
        if (!$isOwn && !$canMod) {
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }
        db_execute('DELETE FROM shoutbox_messages WHERE shout_id = ?', [$id]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log('[shoutbox] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Serverfehler']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
