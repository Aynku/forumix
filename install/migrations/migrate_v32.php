<?php
/**
 * v1.11.0 – Sprachen im Adminbereich verwalten
 *
 * Bisher stehen alle Texte in lang/de.php und lang/en.php. Wer ein Wort
 * anders haben will — "Crew" statt "Mitglieder" — muss die Datei im Paket
 * aendern, und beim naechsten Update ist die Aenderung weg.
 *
 * Zwei Tabellen loesen das, nach demselben Muster wie template_overrides:
 *
 *  text_overrides  Einzelne geaenderte Texte. Die Datei bleibt das Original,
 *                  die Datenbank legt sich darueber. `original_beim_speichern`
 *                  haelt fest, wie der Text im Paket aussah — damit das Forum
 *                  merkt, wenn ein Update ihn geaendert hat, und der Betreiber
 *                  seine Anpassung nicht stillschweigend veralten laesst.
 *
 *  sprachen        Sprachen, die es nur in der Datenbank gibt. Damit laesst
 *                  sich eine dritte Sprache anlegen, ohne eine Datei ins
 *                  Paket zu legen.
 *
 * WARUM keine Datei geschrieben wird: lang/de.php ist ausfuehrbares PHP. Was
 * aus dem Browser kommt, darf dort niemals landen — sonst waere das Feld fuer
 * einen Text in Wahrheit ein Feld fuer Code.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

$tabelleDa = function (string $name): bool {
    try { return (bool) db_fetch("SHOW TABLES LIKE '$name'"); }
    catch (Throwable $e) { return false; }
};

if ($tabelleDa('text_overrides')) {
    $zeile('Tabelle text_overrides war schon da.');
} else {
    db_execute("
        CREATE TABLE text_overrides (
            sprache                 VARCHAR(8)   NOT NULL,
            schluessel              VARCHAR(190) NOT NULL,
            text                    TEXT         NOT NULL,
            original_beim_speichern TEXT             NULL,
            updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
            updated_by              INT              NULL,
            PRIMARY KEY (sprache, schluessel),
            KEY idx_sprache (sprache)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $zeile('Tabelle text_overrides angelegt.');
}

if ($tabelleDa('sprachen')) {
    $zeile('Tabelle sprachen war schon da.');
} else {
    db_execute("
        CREATE TABLE sprachen (
            code       VARCHAR(8)   NOT NULL,
            name       VARCHAR(60)  NOT NULL,
            aktiv      TINYINT(1)   NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $zeile('Tabelle sprachen angelegt.');
}

$zeile('Fertig. Sprachen stehen jetzt unter Darstellung → Sprachen.');
