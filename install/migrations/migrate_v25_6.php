<?php
/**
 * v0.25.6 – Zwei-Faktor: verbrauchte Codes vermerken
 *
 * verify_totp() akzeptierte einen Code innerhalb von +/-1 Zeitfenster, merkte
 * sich aber nicht, dass er benutzt wurde. Derselbe sechsstellige Code war
 * dadurch bis zu 90 Sekunden lang beliebig oft einloesbar.
 *
 * Das genuegte fuer eine vollstaendige Kontouebernahme: forgot.php gibt eine
 * Passwort-Ruecksetzung allein gegen Benutzername + gueltigen Code heraus.
 * Ein einziger abgefangener Code reichte, um das Passwort zu setzen, sich
 * anzumelden und im Zwei-Faktor-Schritt DENSELBEN Code noch einmal zu
 * verwenden.
 *
 * Die neue Spalte haelt das zuletzt akzeptierte Zeitfenster. Ein Code wird nur
 * angenommen, wenn sein Fenster groesser ist als das gespeicherte.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$row = db_fetch("SHOW COLUMNS FROM user_2fa LIKE 'last_window'");

if ($row) {
    echo '<div class="req-row">user_2fa.last_window ist bereits vorhanden – nichts zu tun.</div>';
} else {
    db_execute('ALTER TABLE user_2fa ADD COLUMN `last_window` BIGINT DEFAULT NULL AFTER `secret`');
    echo '<div class="req-row">user_2fa.last_window angelegt – verbrauchte Codes werden ab jetzt vermerkt.</div>';
}
