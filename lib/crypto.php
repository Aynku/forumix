<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/crypto.php – Verschluesselung fuer Werte, die in der Datenbank liegen
//
// Wofuer das gut ist und wofuer nicht:
//
// Es schuetzt gegen den Fall, dass jemand die DATENBANK bekommt, aber nicht
// die Dateien — ein weitergereichter Abzug, eine Sicherung auf einem fremden
// Rechner, eine SQL-Luecke. Das ist der haeufigste Fall.
//
// Es schuetzt NICHT, wenn jemand den Server hat. Dann hat er die config.php
// und damit den Schluessel. Wer etwas anderes verspricht, luegt.
//
// Deshalb liegt der Schluessel in der config.php und nicht in der Datenbank:
// das Setting `forum_secret` waere als Schluessel wertlos, es steht in
// derselben Datenbank wie das, was es schuetzen soll.

// Was verschluesselt wurde, traegt dieses Vorzeichen. Daran erkennt das Lesen,
// ob ein Wert noch aus der Zeit vor dem Schluessel stammt.
const CRYPT_PREFIX = 'enc:v1:';

/**
 * Steht ein brauchbarer Schluessel bereit?
 *
 * Bestehende Installationen haben keinen — bei denen bleibt alles wie vorher,
 * im Klartext. Lieber weiterlaufen als jemanden aus seinem Mailversand
 * aussperren.
 */
function forum_crypt_available(): bool {
    return defined('FORUM_CRYPT_KEY')
        && is_string(FORUM_CRYPT_KEY)
        && strlen(FORUM_CRYPT_KEY) === 64
        && ctype_xdigit(FORUM_CRYPT_KEY)
        && function_exists('openssl_encrypt');
}

/**
 * Verschluesselt einen Wert fuer die Datenbank.
 * Ohne Schluessel kommt der Wert unveraendert zurueck.
 */
function forum_encrypt(string $plain): string {
    if ($plain === '' || !forum_crypt_available()) return $plain;

    $key = hex2bin(FORUM_CRYPT_KEY);
    $iv  = random_bytes(12);            // GCM will genau 12 Bytes
    $tag = '';

    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return $plain;   // lieber Klartext als Datenverlust

    // GCM statt CBC: das Ergebnis traegt seine eigene Pruefsumme. Ein
    // veraenderter Wert entschluesselt nicht falsch, er entschluesselt gar nicht.
    return CRYPT_PREFIX . base64_encode($iv . $tag . $cipher);
}

/**
 * Liest einen Wert aus der Datenbank.
 * Alles ohne Vorzeichen kommt unveraendert zurueck — so laufen alte
 * Installationen weiter, auch nachdem ein Schluessel dazugekommen ist.
 */
function forum_decrypt(string $value): string {
    if (!str_starts_with($value, CRYPT_PREFIX)) return $value;
    if (!forum_crypt_available()) {
        // Verschluesselt abgelegt, aber der Schluessel fehlt jetzt. Das ist ein
        // echter Fehler und kein Grund, stillschweigend Muell zurueckzugeben.
        error_log('[crypto] verschluesselter Wert, aber FORUM_CRYPT_KEY fehlt oder ist ungueltig');
        return '';
    }

    $roh = base64_decode(substr($value, strlen(CRYPT_PREFIX)), true);
    if ($roh === false || strlen($roh) < 29) return '';

    $iv     = substr($roh, 0, 12);
    $tag    = substr($roh, 12, 16);
    $cipher = substr($roh, 28);

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', hex2bin(FORUM_CRYPT_KEY),
                             OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        error_log('[crypto] Wert liess sich nicht entschluesseln — falscher Schluessel?');
        return '';
    }
    return $plain;
}

/**
 * Erzeugt einen Schluessel im richtigen Format. Der Installer schreibt ihn in
 * die config.php; fuer bestehende Installationen gibt der Adminbereich einen
 * fertigen Vorschlag aus.
 */
function forum_crypt_new_key(): string {
    return bin2hex(random_bytes(32));
}
