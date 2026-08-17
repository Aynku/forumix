<?php
/**
 * lib/email_change.php – Doppel-Opt-In für E-Mail-Adressen-Änderung
 *
 * Wenn ein User seine E-Mail ändern will:
 * 1. Funktion `email_change_initiate()` aufrufen mit (user_id, neue_mail)
 *    → Speichert neue Mail in pending_email-Spalte (NICHT überschreibt email)
 *    → Generiert Token (24h gültig)
 *    → Verschickt Verifikations-Mail an die NEUE Adresse
 *    → Alte E-Mail bleibt aktiv bis Bestätigung
 *
 * 2. User klickt Link in Mail → /verify_email_change.php?token=...
 *    → Funktion `email_change_verify($token)` aufrufen
 *    → Wenn gültig: email = pending_email, pending_* = NULL
 *    → User ist umgezogen
 *
 * 3. Optional: User kann offene Anfrage abbrechen via `email_change_cancel()`
 *
 * Sicherheit:
 * - Token ist HMAC-signiert (wie unsubscribe_token.php)
 * - Token wird auch in DB gespeichert für Server-seitige Sperre nach Verbrauch
 *   (1-Klick-Verifikation; sobald genutzt, ist Token weg)
 * - 24h Gültigkeit (kürzer als Unsubscribe-Token, weil sensibler)
 * - Eindeutigkeit der Ziel-Adresse: keine zwei User können die gleiche E-Mail
 *   gleichzeitig "pending" haben (UNIQUE auf email greift erst beim finalen
 *   Umzug, aber wir prüfen es vorher manuell)
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

require_once __DIR__ . '/unsubscribe_token.php';  // wir nutzen die _b64url-Helpers

/**
 * Holt das Secret für E-Mail-Change-Tokens.
 * Eigenes Secret (nicht das vom Unsubscribe), damit Token-Typen nicht
 * verwechselbar sind.
 */
function email_change_secret(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $secret = (string) get_setting('email_change_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        try {
            db_execute(
                "INSERT INTO settings (setting_key, setting_value) VALUES ('email_change_secret', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$secret]
            );
        } catch (Exception $e) {}
    }
    $cached = $secret;
    return $secret;
}

/**
 * Startet einen E-Mail-Change-Vorgang.
 * Returns: ['ok' => true] bei Erfolg, ['ok' => false, 'error' => '...'] bei Fehler.
 */
function email_change_initiate(int $userId, string $newEmail): array {
    $newEmail = trim($newEmail);

    // Validierung: gültige E-Mail?
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse eingeben.'];
    }

    // User holen
    $user = db_fetch('SELECT user_id, username, email FROM users WHERE user_id = ?', [$userId]);
    if (!$user) {
        return ['ok' => false, 'error' => 'Benutzer nicht gefunden.'];
    }

    // Gleiche Adresse wie aktuell?
    if (strcasecmp($newEmail, (string) $user['email']) === 0) {
        return ['ok' => false, 'error' => 'Diese E-Mail-Adresse ist bereits hinterlegt.'];
    }

    // E-Mail schon von einem anderen Konto belegt?
    //
    // Die Antwort darf das nicht verraten: sonst ist das Feld eine Auskunft
    // darueber, wer hier ein Konto hat — man tippt Adressen ein und liest ab,
    // welche belegt sind. Deshalb bricht der Vorgang still ab und meldet
    // dasselbe wie ein Erfolg. Die Bestaetigungsmail geht dann eben nicht
    // raus, und der Wechsel findet nicht statt.
    $existing = db_fetch('SELECT user_id FROM users WHERE email = ? AND user_id != ?',
        [$newEmail, $userId]);
    if ($existing) {
        return ['ok' => true, 'stillschweigend' => true];
    }

    // Token erzeugen: HMAC über (user_id, new_email, expiry)
    $expiry = time() + (24 * 3600);  // 24 Stunden
    $payload = $userId . ':' . $newEmail . ':' . $expiry;
    $hmac = hash_hmac('sha256', $payload, email_change_secret(), true);
    $token = _b64url_encode($payload) . '.' . _b64url_encode($hmac);

    // In DB speichern (überschreibt vorherige offene Anfragen)
    try {
        db_execute(
            'UPDATE users
                SET pending_email = ?, pending_email_token = ?, pending_email_expires = FROM_UNIXTIME(?)
              WHERE user_id = ?',
            [$newEmail, $token, $expiry, $userId]
        );
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Datenbank-Fehler. Bitte später erneut versuchen.'];
    }

    // Verifikations-Mail an die NEUE Adresse versenden
    if (!_send_email_change_verify($userId, (string) $user['username'], $newEmail, $token)) {
        return ['ok' => false, 'error' => 'Verifikations-Mail konnte nicht gesendet werden.'];
    }

    // Optional: alte E-Mail informieren (Security-Awareness)
    _send_email_change_notice($userId, (string) $user['username'],
        (string) $user['email'], $newEmail);

    return ['ok' => true];
}

/**
 * Verifiziert einen Change-Token und führt den Umzug aus.
 * Returns: ['ok' => true, 'new_email' => '...'] oder ['ok' => false, 'error' => '...'].
 */
function email_change_verify(string $token): array {
    if (!str_contains($token, '.')) {
        return ['ok' => false, 'error' => 'Ungültiger Link.'];
    }

    [$payloadEnc, $hmacEnc] = explode('.', $token, 2);
    $payload = _b64url_decode($payloadEnc);
    $hmac    = _b64url_decode($hmacEnc);
    if ($payload === false || $hmac === false) {
        return ['ok' => false, 'error' => 'Ungültiger Link.'];
    }

    // HMAC-Check
    $expected = hash_hmac('sha256', $payload, email_change_secret(), true);
    if (!hash_equals($expected, $hmac)) {
        return ['ok' => false, 'error' => 'Ungültiger Link (Signatur falsch).'];
    }

    // Payload parsen
    $parts = explode(':', $payload, 3);
    if (count($parts) !== 3) {
        return ['ok' => false, 'error' => 'Ungültiger Link (Format).'];
    }
    [$userId, $newEmail, $expiry] = $parts;
    $userId = (int) $userId;
    $expiry = (int) $expiry;

    if ($expiry < time()) {
        return ['ok' => false, 'error' => 'Der Link ist abgelaufen. Bitte E-Mail-Änderung erneut starten.'];
    }

    // Token MUSS noch in DB als pending sein (sonst: schon verbraucht oder abgebrochen)
    $user = db_fetch(
        'SELECT user_id, pending_email, pending_email_token, pending_email_expires
           FROM users WHERE user_id = ?',
        [$userId]
    );
    if (!$user) {
        return ['ok' => false, 'error' => 'Benutzer nicht gefunden.'];
    }
    if (empty($user['pending_email_token']) || !hash_equals((string) $user['pending_email_token'], $token)) {
        return ['ok' => false, 'error' => 'Dieser Link ist nicht mehr gültig (eventuell schon benutzt oder ein neuer wurde angefordert).'];
    }
    if ($user['pending_email'] !== $newEmail) {
        return ['ok' => false, 'error' => 'Daten-Inkonsistenz.'];
    }

    // E-Mail darf nicht inzwischen anderweitig vergeben sein
    $clash = db_fetch('SELECT user_id FROM users WHERE email = ? AND user_id != ?',
        [$newEmail, $userId]);
    if ($clash) {
        return ['ok' => false, 'error' => 'Diese E-Mail-Adresse wurde inzwischen von jemand anderem belegt.'];
    }

    // Umzug ausführen
    try {
        db_execute(
            'UPDATE users
                SET email = ?,
                    pending_email = NULL,
                    pending_email_token = NULL,
                    pending_email_expires = NULL,
                    email_verified = 1
              WHERE user_id = ?',
            [$newEmail, $userId]
        );
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Datenbank-Fehler beim Umzug.'];
    }

    return ['ok' => true, 'new_email' => $newEmail];
}

/**
 * Bricht eine laufende Änderungs-Anfrage ab (User klickt "Abbrechen" in Settings).
 */
function email_change_cancel(int $userId): bool {
    try {
        db_execute(
            'UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL
              WHERE user_id = ?',
            [$userId]
        );
        return true;
    } catch (Exception $e) { return false; }
}

/**
 * Versendet die Verifikations-Mail an die NEUE E-Mail-Adresse.
 */
function _send_email_change_verify(int $userId, string $username, string $newEmail, string $token): bool {
    if (!function_exists('send_email')) return false;

    $forumName = defined('FORUM_NAME') ? FORUM_NAME : 'Forum';
    $verifyUrl = FORUM_URL . '/verify_email_change.php?token=' . urlencode($token);

    $subject = '[' . $forumName . '] E-Mail-Adresse bestätigen';

    $bodyInner = '
        <p>Hallo <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>du hast angefragt, deine E-Mail-Adresse auf
           <strong>' . htmlspecialchars($newEmail, ENT_QUOTES, 'UTF-8') . '</strong>
           zu ändern.</p>
        <p>Bitte klicke auf den folgenden Button, um die neue Adresse zu bestätigen.
           Erst dann wird sie aktiv. Bis dahin bleibt deine alte E-Mail-Adresse gültig.</p>
        <p style="margin:24px 0">
            <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '"
               style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">
                ✅ E-Mail-Adresse bestätigen
            </a>
        </p>
        <p style="font-size:13px;color:#666">
            Dieser Link ist 24 Stunden gültig.<br>
            Falls du diese Änderung nicht angefordert hast, kannst du diese Mail ignorieren —
            ohne Bestätigung passiert nichts.
        </p>
    ';

    $body = email_template('E-Mail-Adresse bestätigen', $bodyInner);
    return send_email($newEmail, $subject, $body, true);
}

/**
 * Informiert die ALTE E-Mail-Adresse über die Änderungs-Anfrage (Security-Hinweis).
 * Soft-Best-Effort: wir loggen es nicht, wenn es scheitert.
 */
function _send_email_change_notice(int $userId, string $username, string $oldEmail, string $newEmail): void {
    if (!function_exists('send_email')) return;
    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) return;

    $forumName = defined('FORUM_NAME') ? FORUM_NAME : 'Forum';
    $subject = '[' . $forumName . '] Hinweis: E-Mail-Änderung angefordert';

    $bodyInner = '
        <p>Hallo <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>eine Änderung deiner E-Mail-Adresse wurde angefordert:</p>
        <ul style="line-height:1.8">
            <li><strong>Bisher:</strong> ' . htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Neu (noch nicht aktiv):</strong> ' . htmlspecialchars($newEmail, ENT_QUOTES, 'UTF-8') . '</li>
        </ul>
        <p>Bis du die neue Adresse über einen Link in einer separaten Mail bestätigst,
           bleibt deine bisherige Adresse aktiv.</p>
        <p style="margin-top:18px;padding:12px;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px">
            ⚠️ <strong>Warst du das nicht?</strong>
            Logge dich sofort in dein Konto ein, gehe zu Einstellungen → Sicherheit
            und brich die Änderung ab. Ändere zur Sicherheit auch dein Passwort.
        </p>
        <p style="font-size:13px;color:#666;margin-top:18px">
            Solange du keinen Bestätigungs-Link an der neuen Adresse klickst,
            passiert nichts. Du musst diese Mail nicht beantworten.
        </p>
    ';

    $body = email_template('Hinweis: E-Mail-Änderung angefordert', $bodyInner);
    @send_email($oldEmail, $subject, $body, true);
}
