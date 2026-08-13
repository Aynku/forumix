<?php
/**
 * lib/unsubscribe_token.php – 1-Klick-Abmelde-Tokens
 *
 * Erzeugt und verifiziert HMAC-signierte Tokens für „eine-Klick-Abmeldung"-Links
 * in Benachrichtigungs-Mails. Stateless – kein DB-Eintrag pro Token nötig.
 *
 * Format: base64url(payload) . "." . base64url(hmac)
 *   payload = "user_id:thread_id:expiry_ts"
 *   hmac    = hmac_sha256(payload, secret)
 *
 * Default-Gültigkeit: 30 Tage. Danach Token abgelaufen → User muss Settings nutzen.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Holt das Forum-spezifische Secret für HMAC-Signierung.
 * Wird beim ersten Aufruf automatisch generiert und in der settings-Tabelle gespeichert.
 */
function unsubscribe_secret(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $secret = (string) get_setting('unsubscribe_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));  // 64-Zeichen-Hex
        try {
            db_execute(
                "INSERT INTO settings (setting_key, setting_value) VALUES ('unsubscribe_secret', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$secret]
            );
        } catch (Exception $e) {
            // Wenn DB-Schreiben fehlschlägt, nutzen wir das Secret trotzdem für diese Request.
            // Beim nächsten Aufruf wird's nochmal versucht.
        }
    }
    $cached = $secret;
    return $secret;
}

/**
 * Erzeugt einen 1-Klick-Abmelde-Token für (user_id, thread_id).
 * Token ist 30 Tage gültig.
 */
function unsubscribe_token_create(int $userId, int $threadId): string {
    $expiry  = time() + (30 * 24 * 3600);  // 30 Tage
    $payload = $userId . ':' . $threadId . ':' . $expiry;
    $hmac    = hash_hmac('sha256', $payload, unsubscribe_secret(), true);
    return _b64url_encode($payload) . '.' . _b64url_encode($hmac);
}

/**
 * Verifiziert einen Token und gibt ['user_id' => int, 'thread_id' => int] zurück
 * bei Erfolg, sonst null.
 */
function unsubscribe_token_verify(string $token): ?array {
    if (!str_contains($token, '.')) return null;
    [$payloadEnc, $hmacEnc] = explode('.', $token, 2);
    $payload = _b64url_decode($payloadEnc);
    $hmac    = _b64url_decode($hmacEnc);
    if ($payload === false || $hmac === false) return null;

    // HMAC neu berechnen und konstante-Zeit-Vergleich
    $expected = hash_hmac('sha256', $payload, unsubscribe_secret(), true);
    if (!hash_equals($expected, $hmac)) return null;

    // Payload parsen
    $parts = explode(':', $payload);
    if (count($parts) !== 3) return null;
    [$userId, $threadId, $expiry] = $parts;
    if (!ctype_digit($userId) || !ctype_digit($threadId) || !ctype_digit($expiry)) return null;
    if ((int) $expiry < time()) return null;  // Abgelaufen

    return [
        'user_id'   => (int) $userId,
        'thread_id' => (int) $threadId,
    ];
}

/** base64url-Kodierung (URL-sicher, ohne Padding) */
function _b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** base64url-Dekodierung */
function _b64url_decode(string $data): string|false {
    $data = strtr($data, '-_', '+/');
    $pad  = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode($data, true);
}
