<?php
/**
 * v1.2.0 – Woher eine Gruppenmitgliedschaft kommt
 *
 * Gruppen lassen sich ab dieser Fassung aus einer Adressliste pflegen
 * (tools/kundenabgleich.php). Damit gibt es zwei Quellen fuer dieselbe Zeile
 * in `group_members`: von Hand eingetragen, oder aus der Liste uebernommen.
 *
 * Ohne diesen Unterschied waere der Abgleich gefaehrlich: er muesste jeden
 * entfernen, der nicht auf der Liste steht — auch den, den der Betreiber
 * gerade von Hand hineingesetzt hat. Mit der Spalte fasst er nur an, was er
 * selbst angelegt hat.
 *
 * Vorgabe 'hand': alles Bestehende ist von Hand entstanden, denn etwas
 * anderes gab es bisher nicht.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

$spalte = db_fetch("SHOW COLUMNS FROM group_members LIKE 'woher'");
if (!$spalte) {
    db_execute("ALTER TABLE group_members
                ADD COLUMN woher ENUM('hand','liste') NOT NULL DEFAULT 'hand' AFTER added_at");
    $zeile('group_members: Spalte woher angelegt (Bestand gilt als von Hand).');
} else {
    $zeile('group_members: Spalte woher war schon da.');
}
