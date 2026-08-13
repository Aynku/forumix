<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/password_reset.php

function create_password_reset(int $userId): ?string {
    try {
        // Alte Tokens löschen
        db_execute('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()', [$userId]);

        $token = bin2hex(random_bytes(32));
        db_execute(
            'INSERT INTO password_resets (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))',
            [$token, $userId]
        );
        return $token;
    } catch (Exception $e) { return null; }
}

function verify_reset_token(string $token): ?array {
    try {
        return db_fetch(
            'SELECT pr.*, u.username, u.email FROM password_resets pr
             JOIN users u ON u.user_id = pr.user_id
             WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()',
            [$token]
        );
    } catch (Exception $e) { return null; }
}

function consume_reset_token(string $token): void {
    try {
        db_execute('UPDATE password_resets SET used = 1 WHERE token = ?', [$token]);
    } catch (Exception $e) {}
}

function send_password_reset_email(array $user, string $token): bool {
    $link = FORUM_URL . '/reset.php?token=' . $token;
    $body = email_template('Passwort zurücksetzen', '
        <p>Hallo ' . e($user['username']) . ',</p>
        <p>du hast einen Link angefordert, um dein Passwort zurückzusetzen.</p>
        <p>Klicke innerhalb der nächsten 2 Stunden auf folgenden Link:</p>
        <p style="margin:24px 0">
            <a href="' . e($link) . '"
               style="display:inline-block;padding:12px 24px;background:#1a1a1a;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold">
                Passwort jetzt zurücksetzen
            </a>
        </p>
        <p style="font-size:12px;color:#666">Falls der Button nicht funktioniert, kopiere bitte diesen Link in deinen Browser:<br>
        <code style="font-size:11px;word-break:break-all">' . e($link) . '</code></p>
        <p style="font-size:12px;color:#666;margin-top:24px;padding-top:16px;border-top:1px solid #ddd">
            ℹ️ Falls du keine Anfrage gestellt hast, ignoriere diese E-Mail einfach.
        </p>
    ');
    return send_email($user['email'], 'Passwort zurücksetzen', $body);
}
