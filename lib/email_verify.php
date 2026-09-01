<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/email_verify.php

function create_email_verification(int $userId, string $email): ?string {
    try {
        db_execute('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
        $token = bin2hex(random_bytes(32));
        db_execute(
            'INSERT INTO email_verifications (token, user_id, email, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))',
            [$token, $userId, $email]
        );
        return $token;
    } catch (Exception $e) { return null; }
}

function verify_email_token(string $token): ?array {
    try {
        return db_fetch(
            'SELECT ev.*, u.username FROM email_verifications ev
             JOIN users u ON u.user_id = ev.user_id
             WHERE ev.token = ? AND ev.expires_at > NOW()',
            [$token]
        );
    } catch (Exception $e) { return null; }
}

function activate_email_verification(string $token): bool {
    $v = verify_email_token($token);
    if (!$v) return false;
    try {
        db_execute('UPDATE users SET email_verified = 1, email = ? WHERE user_id = ?',
            [$v['email'], $v['user_id']]);
        db_execute('DELETE FROM email_verifications WHERE user_id = ?', [$v['user_id']]);
        return true;
    } catch (Exception $e) { return false; }
}

function send_verification_email(array $user, string $token): bool {
    $link = FORUM_URL . '/verify.php?token=' . $token;
    $body = email_template(t('sys_e_mail_bestaetigen'), '
        <p>Hallo ' . e($user['username']) . ',</p>
        <p>willkommen bei ' . e(FORUM_NAME) . '! Um dein Konto zu aktivieren, bestätige bitte deine E-Mail-Adresse:</p>
        <p style="margin:24px 0">
            <a href="' . e($link) . '"
               style="display:inline-block;padding:12px 24px;background:#1a1a1a;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold">
                E-Mail bestätigen
            </a>
        </p>
        <p style="font-size:12px;color:#666">Falls der Button nicht funktioniert:<br>
        <code style="font-size:11px;word-break:break-all">' . e($link) . '</code></p>
        <p style="font-size:12px;color:#666;margin-top:24px">Der Link ist 24 Stunden gültig.</p>
    ');
    return send_email($user['email'], t('sys_e_mail_bestaetigen_2') . FORUM_NAME, $body);
}
