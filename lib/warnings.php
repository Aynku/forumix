<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/warnings.php – Warnungssystem

function issue_warning(int $userId, int $modId, int $points, string $reason, ?string $expiresAt = null): int {
    $points = max(1, min(20, $points));
    $exp = null;
    if ($expiresAt) {
        $ts = strtotime($expiresAt);
        if ($ts) $exp = date('Y-m-d H:i:s', $ts);
    }

    try {
        $wid = db_execute(
            'INSERT INTO warnings (user_id, mod_id, points, reason, expires_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $modId, $points, mb_substr($reason, 0, 255), $exp]
        );

        // Aktiv-Punkte neu berechnen
        recalc_warning_points($userId);

        // Auto-Bann prüfen
        check_warning_threshold($userId, $modId);

        // Mod-Log
        try {
            db_execute(
                'INSERT INTO mod_log (mod_id, action, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?)',
                [$modId, 'warning', 'user', $userId, $points . ' Punkte – ' . $reason]
            );
        } catch (Exception $e) {}

        // User benachrichtigen
        try {
            create_notification($userId, 'warning', [
                'from_user_id' => $modId,
            ]);
        } catch (Exception $e) {}

        return (int) $wid;
    } catch (Exception $e) { return 0; }
}

function recalc_warning_points(int $userId): int {
    try {
        $pts = (int) db_count(
            'SELECT IFNULL(SUM(points), 0) FROM warnings
             WHERE user_id = ? AND is_active = 1
             AND (expires_at IS NULL OR expires_at > NOW())',
            [$userId]
        );
        db_execute('UPDATE users SET warning_points = ? WHERE user_id = ?', [$pts, $userId]);
        return $pts;
    } catch (Exception $e) { return 0; }
}

function check_warning_threshold(int $userId, int $modId): void {
    $threshold = (int) get_setting('warning_ban_threshold', 10);
    if ($threshold <= 0) return;

    $pts = recalc_warning_points($userId);
    if ($pts < $threshold) return;

    try {
        $u = db_fetch('SELECT is_banned, is_admin FROM users WHERE user_id = ?', [$userId]);
        if (!$u || $u['is_admin'] || $u['is_banned']) return;

        db_execute(
            'UPDATE users SET is_banned = 1, ban_reason = ? WHERE user_id = ?',
            ['Automatisch gesperrt: ' . $pts . ' Warnungs-Punkte', $userId]
        );
        db_execute(
            'INSERT INTO mod_log (mod_id, action, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?)',
            [$modId, 'auto_ban', 'user', $userId, 'Warnungs-Schwelle (' . $pts . ' Pkt) überschritten']
        );
    } catch (Exception $e) {}
}

function user_warnings(int $userId): array {
    try {
        return db_fetchAll('
            SELECT w.*, m.username AS mod_name
            FROM warnings w JOIN users m ON m.user_id = w.mod_id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ', [$userId]);
    } catch (Exception $e) { return []; }
}

function revoke_warning(int $warningId, int $modId): bool {
    try {
        $w = db_fetch('SELECT user_id FROM warnings WHERE warning_id = ?', [$warningId]);
        if (!$w) return false;
        db_execute('UPDATE warnings SET is_active = 0 WHERE warning_id = ?', [$warningId]);
        recalc_warning_points((int) $w['user_id']);
        db_execute(
            'INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
            [$modId, 'revoke_warning', 'user', (int) $w['user_id']]
        );
        return true;
    } catch (Exception $e) { return false; }
}
