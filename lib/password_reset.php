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
    $body = email_template(t('sys_passwort_zuruecksetzen'), '
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
    return send_email($user['email'], t('sys_passwort_zuruecksetzen'), $body);
}

/**
 * Meldet dem Kontoinhaber, dass eine Ruecksetzung ohne Postfachzugriff
 * angestossen wurde — ueber Zwei-Faktor oder einen Ersatzcode.
 *
 * Warum: bei diesen beiden Wegen genuegen Benutzername und ein Code. Ohne
 * diese Nachricht merkt der Inhaber die Uebernahme erst, wenn sein Passwort
 * nicht mehr passt. Die Mail enthaelt bewusst KEINEN Link — sie warnt nur.
 */
function melde_ruecksetzung_ohne_postfach(int $userId, string $weg): void {
    try {
        $u = db_fetch('SELECT username, email FROM users WHERE user_id = ?', [$userId]);
        if (!$u || empty($u['email'])) return;

        $bezeichnung = $weg === 'backup' ? 'einen Ersatzcode' : 'den Zwei-Faktor-Code';
        $betreff = t('sys_passwort_ruecksetzung_angefordert');
        $text = '<p>Hallo ' . e($u['username']) . ',</p>'
              . '<p>fuer dein Konto wurde soeben eine Passwort-Ruecksetzung ueber '
              . $bezeichnung . ' angefordert. Warst du das, kannst du diese '
              . 'Nachricht ignorieren.</p>'
              . '<p><strong>Warst du das nicht</strong>, ist dein zweiter Faktor in '
              . 'fremden Haenden. Melde dich an, richte die Zwei-Faktor-Anmeldung '
              . 'neu ein und vergib neue Ersatzcodes.</p>';

        send_email($u['email'], $betreff, $text);
    } catch (Throwable $e) {
        // Der Versand darf den Vorgang nicht aufhalten.
        error_log('[reset] Warnmail fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Kurzer Name fuer den Aufruf aus pages/forgot.php.
 */
function passwort_ruecksetzung_melden(int $userId, string $weg): void {
    melde_ruecksetzung_ohne_postfach($userId, $weg);
}
