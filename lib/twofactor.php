<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/twofactor.php – TOTP (Google Authenticator kompatibel)

/**
 * Base32-Encoding (für QR-Code-Secrets)
 */
function base32_encode(string $data): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $buffer = 0;
    $bitsLeft = 0;
    for ($i = 0; $i < strlen($data); $i++) {
        $buffer = ($buffer << 8) | ord($data[$i]);
        $bitsLeft += 8;
        while ($bitsLeft >= 5) {
            $out .= $chars[($buffer >> ($bitsLeft - 5)) & 0x1F];
            $bitsLeft -= 5;
        }
    }
    if ($bitsLeft > 0) $out .= $chars[($buffer << (5 - $bitsLeft)) & 0x1F];
    return $out;
}

function base32_decode(string $b32): string {
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bitsLeft = 0;
    $out = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $val = strpos($chars, $b32[$i]);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $out .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
            $bitsLeft -= 8;
        }
    }
    return $out;
}

/**
 * Generiert einen neuen 2FA-Secret (Base32-codiert)
 */
function generate_2fa_secret(): string {
    return base32_encode(random_bytes(20));
}

/**
 * Berechnet aktuellen TOTP-Code für einen Secret
 */
function totp_code(string $secret, ?int $timeStep = null): string {
    $key = base32_decode($secret);
    $time = ($timeStep ?? floor(time() / 30));
    $bin  = pack('N*', 0) . pack('N*', $time);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0xF;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

/**
 * Prüft einen 6-stelligen Code gegen den Secret.
 * Erlaubt ±1 Zeitfenster Toleranz (Clock Skew).
 */
function verify_totp(string $secret, string $code): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $now = floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code($secret, $now + $i), $code)) return true;
    }
    return false;
}

/**
 * URL für QR-Code (kann via externes QR-Tool gerendert werden)
 */
function twofa_otpauth_url(string $username, string $secret): string {
    $issuer = rawurlencode(FORUM_NAME);
    $label  = rawurlencode($username);
    return 'otpauth://totp/' . $issuer . ':' . $label
         . '?secret=' . $secret . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
}

function user_has_2fa(int $userId): bool {
    try {
        return db_count('SELECT COUNT(*) FROM user_2fa WHERE user_id = ? AND enabled = 1', [$userId]) > 0;
    } catch (Exception $e) { return false; }
}

function generate_backup_codes(int $count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

function verify_backup_code(int $userId, string $code): bool {
    $code = strtoupper(trim(preg_replace('/\s/', '', $code)));
    try {
        $row = db_fetch('SELECT backup_codes FROM user_2fa WHERE user_id = ?', [$userId]);
        if (!$row || !$row['backup_codes']) return false;
        $codes = json_decode($row['backup_codes'], true);
        if (!is_array($codes)) return false;
        $idx = array_search($code, $codes, true);
        if ($idx === false) return false;
        unset($codes[$idx]);
        db_execute('UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?',
            [json_encode(array_values($codes)), $userId]);
        return true;
    } catch (Exception $e) { return false; }
}
