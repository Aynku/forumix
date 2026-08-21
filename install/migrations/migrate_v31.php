<?php
/**
 * v1.6.2 – Eine Gruppe kann den Rang ersetzen
 *
 * Der Rang wird aus der Beitragszahl berechnet. Fuer ein Konto, das gar nicht
 * mitschreibt, sondern etwas ankuendigt — einen Bot etwa — ergibt "Neuling"
 * keinen Sinn, und weglassen liess er sich bisher nicht: bei Admins und
 * Moderatoren tritt ihr Abzeichen an seine Stelle, aber das haengt an festen
 * Kennzeichen, nicht an Gruppen.
 *
 * Mit dieser Spalte kann eine Gruppe dasselbe: Ist sie gesetzt, steht das
 * Gruppen-Abzeichen dort, wo sonst der Rang stuende.
 *
 * VORGABE: aus. Bestehende Gruppen verhalten sich wie bisher.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

try {
    $da = (bool) db_fetch("SHOW TABLES LIKE 'user_groups'");
} catch (Throwable $e) {
    $da = false;
}

if (!$da) {
    $zeile('Tabelle user_groups fehlt — bitte zuerst v1.1.0 (v26) fahren.');
    return;
}

$spalte = null;
try { $spalte = db_fetch("SHOW COLUMNS FROM user_groups LIKE 'hide_rank'"); } catch (Throwable $e) {}

if ($spalte) {
    $zeile('user_groups.hide_rank war schon da.');
} else {
    db_execute("ALTER TABLE user_groups
                ADD COLUMN hide_rank TINYINT(1) NOT NULL DEFAULT 0 AFTER show_badge");
    $zeile('user_groups.hide_rank angelegt (Vorgabe: aus).');
}
