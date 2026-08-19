<?php
/**
 * v1.1.0 – Gruppen und Rechte pro Forum
 *
 * Bis hierher kannte das Forum drei Stufen: Inhaber/Admin/Moderator, Forum-
 * Moderator, und alle anderen. Wer ein Forum nur einem Teil der Mitglieder
 * zeigen wollte, hatte nur den Passwortschutz — und der ist etwas anderes:
 * er ist selbst zu oeffnen, wenn man das Wort kennt, und er verraet, dass es
 * das Forum gibt.
 *
 * Ab jetzt: Gruppen, und je Forum und Gruppe eine Stufe (nichts/lesen/
 * schreiben). Wer nichts darf, sieht das Forum gar nicht — oder sieht es
 * gesperrt, das entscheidet `boards.visibility` pro Forum.
 *
 * ZUR NUMMER: Die Migrationen zaehlen fortlaufend (v25_7 war 0.25.7). "v1_1"
 * fuer 1.1.0 wuerde beim Sortieren VOR v18_56 landen, weil der Sortierschluessel
 * aus der Zahl gebildet wird. Deshalb geht es bei v26 weiter.
 *
 * BESTEHENDE FOREN AENDERN SICH NICHT: Jedes vorhandene Forum bekommt
 * ausdruecklich "Gaeste duerfen lesen, Angemeldete duerfen schreiben"
 * eingetragen — also genau das, was heute gilt.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

// ── 1. Gruppen ──────────────────────────────────────────────────────────
db_execute("
    CREATE TABLE IF NOT EXISTS user_groups (
        group_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name       VARCHAR(60)  NOT NULL,
        slug       VARCHAR(60)  NOT NULL,
        is_builtin TINYINT(1)   NOT NULL DEFAULT 0,
        show_badge TINYINT(1)   NOT NULL DEFAULT 0,
        position   INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (group_id),
        UNIQUE KEY slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$zeile('Tabelle user_groups steht bereit.');

// ── 2. Mitgliedschaften ─────────────────────────────────────────────────
db_execute("
    CREATE TABLE IF NOT EXISTS group_members (
        group_id INT UNSIGNED NOT NULL,
        user_id  INT UNSIGNED NOT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$zeile('Tabelle group_members steht bereit.');

// ── 3. Rechte je Forum und Gruppe ───────────────────────────────────────
db_execute("
    CREATE TABLE IF NOT EXISTS board_permissions (
        board_id INT UNSIGNED NOT NULL,
        group_id INT UNSIGNED NOT NULL,
        level    ENUM('none','read','write') NOT NULL DEFAULT 'none',
        PRIMARY KEY (board_id, group_id),
        KEY group_id (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$zeile('Tabelle board_permissions steht bereit.');

// ── 4. Sichtbarkeit je Forum ────────────────────────────────────────────
$spalte = db_fetch("SHOW COLUMNS FROM boards LIKE 'visibility'");
if (!$spalte) {
    db_execute("ALTER TABLE boards ADD COLUMN visibility ENUM('hidden','locked')
                NOT NULL DEFAULT 'hidden' AFTER password_hash");
    $zeile('boards.visibility angelegt (Vorgabe: verstecken).');
} else {
    $zeile('boards.visibility war schon da.');
}

// ── 5. Die beiden eingebauten Gruppen ───────────────────────────────────
// Jeder Besucher faellt in genau eine davon. Ohne sie braeuchte jede Abfrage
// einen Sonderfall fuer "nicht angemeldet".
foreach ([['guests', 'Gäste', 1], ['members', 'Angemeldete', 2]] as [$slug, $name, $pos]) {
    $da = db_fetch('SELECT group_id FROM user_groups WHERE slug = ?', [$slug]);
    if (!$da) {
        db_execute('INSERT INTO user_groups (name, slug, is_builtin, show_badge, position)
                    VALUES (?, ?, 1, 0, ?)', [$name, $slug, $pos]);
        $zeile('Gruppe "' . $name . '" angelegt.');
    }
}

$gaeste = (int) db_fetch('SELECT group_id FROM user_groups WHERE slug = ?', ['guests'])['group_id'];
$mit    = (int) db_fetch('SELECT group_id FROM user_groups WHERE slug = ?', ['members'])['group_id'];

// ── 6. Bestehende Foren so eintragen, wie sie heute laufen ──────────────
// Nicht "alles zu und der Betreiber macht wieder auf": nach einem Update darf
// kein Forum verschwinden, das gestern noch offen war.
$foren = db_fetchAll('SELECT board_id FROM boards');
$neu = 0;
foreach ($foren as $b) {
    $bid = (int) $b['board_id'];
    $hat = db_count('SELECT COUNT(*) FROM board_permissions WHERE board_id = ?', [$bid]);
    if ($hat > 0) continue;
    db_execute('INSERT INTO board_permissions (board_id, group_id, level) VALUES (?, ?, ?), (?, ?, ?)',
               [$bid, $gaeste, 'read', $bid, $mit, 'write']);
    $neu++;
}
$zeile($neu > 0
    ? $neu . ' Foren eingetragen: Gäste lesen, Angemeldete schreiben — wie bisher.'
    : 'Alle Foren hatten bereits Rechte eingetragen.');
