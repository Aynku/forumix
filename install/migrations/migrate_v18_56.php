<?php
/**
 * v0.18.56 – pending_email_token verbreitern
 *
 * Die Spalte ist VARCHAR(64), der Token aus email_change_initiate() ist
 * base64url(payload) . '.' . base64url(hmac) und damit immer laenger als 64
 * Zeichen — der HMAC-Teil allein belegt schon 43. Folge: im Strict-Modus
 * scheitert das UPDATE ("Data too long") und der Benutzer sieht einen
 * Datenbank-Fehler; ohne Strict-Modus wird abgeschnitten und der Link im
 * Bestaetigungs-Mail kann nie passen. Der E-Mail-Wechsel war damit seit
 * v0.18.48 nicht durchfuehrbar.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$row = db_fetch("SHOW COLUMNS FROM users LIKE 'pending_email_token'");

if (!$row) {
    echo '<div class="req-row">Spalte pending_email_token nicht vorhanden – uebersprungen.</div>';
} elseif (stripos((string) $row['Type'], 'varchar(255)') !== false) {
    echo '<div class="req-row">pending_email_token ist bereits VARCHAR(255) – nichts zu tun.</div>';
} else {
    db_execute('ALTER TABLE users MODIFY `pending_email_token` VARCHAR(255) DEFAULT NULL');
    echo '<div class="req-row">pending_email_token: ' . htmlspecialchars((string) $row['Type'])
       . ' auf VARCHAR(255) erweitert.</div>';
}

// Angefangene Wechsel, deren Token abgeschnitten in der DB steht, sind
// unbrauchbar — sie wuerden nur "Link ungueltig" liefern. Weg damit, der
// Benutzer kann den Wechsel neu anstossen.
$stale = db_execute(
    "UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL
     WHERE pending_email_token IS NOT NULL AND CHAR_LENGTH(pending_email_token) <= 64"
);
echo '<div class="req-row">Abgeschnittene Wechsel-Token verworfen.</div>';
