<?php
/**
 * v1.5.0 – Feinere Rechte je Forum und Gruppe
 *
 * Bisher kannte ein Forum je Gruppe drei Stufen: nichts, lesen, schreiben.
 * "Schreiben" war dabei alles auf einmal — Themen eröffnen, antworten,
 * Dateien anhängen, Umfragen anlegen, den eigenen Text nachträglich ändern.
 *
 * Für ein Ankündigungsforum, in dem alle antworten dürfen, aber nur das Team
 * ein Thema eröffnet, gab es damit keine Einstellung. Ab jetzt zerfällt
 * "schreiben" in vier Einzelrechte.
 *
 * VORGABE: Alle vier stehen auf "erlaubt". Nach dem Update darf jede Gruppe
 * genau das, was sie gestern durfte — eine Verschärfung, die niemand
 * angeordnet hat, wäre hier der falsche Weg.
 *
 * Die Rechte greifen nur bei Stufe "schreiben". Wer nur lesen darf, hängt
 * auch mit gesetztem Haken nichts an.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

// Der Name steht fest im Code, nicht in einer Eingabe — er wird deshalb
// direkt eingesetzt. Mit Platzhalter ginge es nicht: MariaDB bereitet
// SHOW COLUMNS serverseitig vor und lehnt das "?" dort ab. Der Fehler landete
// im catch, die Pruefung meldete "nicht da", und das ALTER TABLE scheiterte
// mit "Duplicate column name" — die Anpassung liess sich kein zweites Mal
// fahren.
$hatSpalte = function (string $tabelle, string $name): bool {
    if (!preg_match('/^[a-z_]+$/', $tabelle) || !preg_match('/^[a-z_]+$/', $name)) return false;
    try {
        return (bool) db_fetch("SHOW COLUMNS FROM `$tabelle` LIKE '$name'");
    } catch (Throwable $e) {
        return false;
    }
};

// Ohne die Tabelle aus v1.1.0 gibt es hier nichts zu tun. Das ist kein
// Fehler: sie kommt aus Migration v26, die dann noch aussteht.
$tabelleDa = false;
try {
    $tabelleDa = (bool) db_fetch("SHOW TABLES LIKE 'board_permissions'");
} catch (Throwable $e) {
    $tabelleDa = false;
}

if (!$tabelleDa) {
    $zeile('Tabelle board_permissions fehlt — bitte zuerst v1.1.0 (v26) fahren.');
    return;
}

$spalten = [
    'can_thread' => 'Themen eröffnen',
    'can_reply'  => 'Antworten',
    'can_attach' => 'Dateien anhängen',
    'can_edit'   => 'Eigene Beiträge bearbeiten',
];

$neu = 0;
foreach ($spalten as $spalte => $klartext) {
    if ($hatSpalte('board_permissions', $spalte)) {
        $zeile('board_permissions.' . $spalte . ' war schon da.');
        continue;
    }
    db_execute("ALTER TABLE board_permissions
                ADD COLUMN `$spalte` TINYINT(1) NOT NULL DEFAULT 1");
    $zeile('board_permissions.' . $spalte . ' angelegt (' . $klartext . ', Vorgabe: erlaubt).');
    $neu++;
}

// Umfragen stehen getrennt, weil sie an einer anderen Stelle im Code hängen:
// nicht am Beitrag, sondern am Themenstart.
if (!$hatSpalte('board_permissions', 'can_poll')) {
    db_execute("ALTER TABLE board_permissions
                ADD COLUMN `can_poll` TINYINT(1) NOT NULL DEFAULT 1");
    $zeile('board_permissions.can_poll angelegt (Umfragen anlegen, Vorgabe: erlaubt).');
    $neu++;
} else {
    $zeile('board_permissions.can_poll war schon da.');
}

$zeile($neu > 0
    ? $neu . ' Rechte ergänzt. Alle stehen auf "erlaubt" — es ändert sich zunächst nichts.'
    : 'Alle Rechte waren bereits eingetragen.');
