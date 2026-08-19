<?php
/**
 * Gruppenrechte pro Forum (1.1.0).
 *
 * Geprueft wird das, was schiefgehen darf und was nicht:
 *   - die Migration legt an und traegt bestehende Foren so ein, wie sie laufen
 *   - die hoechste Stufe aus allen Gruppen gewinnt
 *   - ein Forum ohne Recht verschwindet aus der Filterliste — aber nur, wenn es
 *     auf "verstecken" steht
 *   - der Grund fuer die Abweisung ist unterscheidbar: Passwort kann man selbst
 *     eingeben, eine fehlende Gruppe nicht
 *
 * Faehrt gegen die Testdatenbank und raeumt hinterher auf.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$db     = getenv('FORUM_TEST_DB') ?: 'forumix_test';
$konfig = '/root/forumix-test/instanz/config.php';
if (!is_file($konfig)) return ['Gruppenrechte (uebersprungen, kein Pruefstand)' => fn() => null];

$roh  = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};
$pdo = new PDO("mysql:host={$hole('DB_HOST')};dbname=$db;charset=utf8mb4",
               $hole('DB_USER'), $hole('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$GLOBALS['__pdo'] = $GLOBALS['__pdo'] ?? $pdo;
$GLOBALS['__testNutzer'] = null;

if (!function_exists('db_fetch')) {
    function db_fetch(string $sql, array $p = []) {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('db_fetchAll')) {
    function db_fetchAll(string $sql, array $p = []): array {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('db_execute')) {
    function db_execute(string $sql, array $p = []): bool {
        $s = $GLOBALS['__pdo']->prepare($sql); return $s->execute($p);
    }
}
if (!function_exists('db_count')) {
    function db_count(string $sql, array $p = []): int {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return (int) $s->fetchColumn();
    }
}
/* Die 2FA-Pruefung definiert ihre Helfer nur, wenn db_fetch noch fehlt — und
   diese Datei laeuft alphabetisch vorher. Also gehoert db_affected hier mit
   dazu, sonst steht sie ohne da. */
if (!function_exists('db_affected')) {
    function db_affected(string $sql, array $p = []): int {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->rowCount();
    }
}
if (!function_exists('current_user')) {
    function current_user() { return $GLOBALS['__testNutzer']; }
}

require_once $wurzel . '/lib/board_access.php';

/* Die Zugangsschicht merkt sich Gruppen und Stufen pro Aufruf. Hier wechselt
   der Benutzer mehrfach im selben Vorgang, also vor jeder Messung leeren. */
$frisch = function () { board_access_neu_laden(); };

return [
    'Migration legt Tabellen an und traegt bestehende Foren ein' => function () use ($pdo, $wurzel) {
        $pdo->exec('DROP TABLE IF EXISTS board_permissions');
        $pdo->exec('DROP TABLE IF EXISTS group_members');
        $pdo->exec('DROP TABLE IF EXISTS user_groups');
        try { $pdo->exec('ALTER TABLE boards DROP COLUMN visibility'); } catch (Exception $e) {}

        $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Migration', 90)");
        $cat = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Vorher da', 1)");
        $brett = (int) $pdo->lastInsertId();

        ob_start();
        require $wurzel . '/install/migrations/migrate_v26.php';
        ob_end_clean();

        foreach (['user_groups', 'group_members', 'board_permissions'] as $t) {
            if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetch()) throw new RuntimeException("Tabelle $t fehlt");
        }
        if (!$pdo->query("SHOW COLUMNS FROM boards LIKE 'visibility'")->fetch()) {
            throw new RuntimeException('Spalte boards.visibility fehlt');
        }
        $stufen = $pdo->query("
            SELECT g.slug, p.level FROM board_permissions p
            JOIN user_groups g ON g.group_id = p.group_id
            WHERE p.board_id = $brett")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (($stufen['guests'] ?? '') !== 'read')   throw new RuntimeException('Gäste sollten lesen duerfen');
        if (($stufen['members'] ?? '') !== 'write') throw new RuntimeException('Angemeldete sollten schreiben duerfen');

        $pdo->exec("DELETE FROM board_permissions WHERE board_id = $brett");
        $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
        $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
    },

    'Die hoechste Stufe aus allen Gruppen gewinnt' => function () use ($pdo, $frisch) {
        $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Stufen', 91)");
        $cat = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Gemischt', 1)");
        $brett = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO user_groups (name, slug, position) VALUES ('Kunden', 'pruef_kunden', 50)");
        $kunden = (int) $pdo->lastInsertId();
        $mit = (int) $pdo->query("SELECT group_id FROM user_groups WHERE slug='members'")->fetchColumn();

        $pdo->exec("INSERT INTO users (username, password, email)
                    VALUES ('pruef_stufen', 'x', 'stufen@example.invalid')");
        $uid = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO group_members (group_id, user_id) VALUES ($kunden, $uid)");
        $pdo->exec("INSERT INTO board_permissions (board_id, group_id, level)
                    VALUES ($brett, $mit, 'read'), ($brett, $kunden, 'write')");

        try {
            $GLOBALS['__testNutzer'] = ['user_id' => $uid, 'is_admin' => 0, 'is_mod' => 0, 'is_owner' => 0];
            $frisch();
            $stufe = board_level($brett);
            if ($stufe !== 'write') throw new RuntimeException("Erwartet write, bekommen $stufe");
        } finally {
            $GLOBALS['__testNutzer'] = null;
            $pdo->exec("DELETE FROM board_permissions WHERE board_id = $brett");
            $pdo->exec("DELETE FROM group_members WHERE user_id = $uid");
            $pdo->exec("DELETE FROM users WHERE user_id = $uid");
            $pdo->exec("DELETE FROM user_groups WHERE group_id = $kunden");
            $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
            $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
        }
    },

    'Ohne Recht: versteckt verschwindet, gesperrt bleibt sichtbar' => function () use ($pdo, $frisch) {
        $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Sicht', 92)");
        $cat = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO boards (cat_id, title, position, visibility)
                    VALUES ($cat, 'Versteckt', 1, 'hidden')");
        $v = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO boards (cat_id, title, position, visibility)
                    VALUES ($cat, 'Gesperrt', 2, 'locked')");
        $g = (int) $pdo->lastInsertId();
        $gaeste = (int) $pdo->query("SELECT group_id FROM user_groups WHERE slug='guests'")->fetchColumn();
        $pdo->exec("INSERT INTO board_permissions (board_id, group_id, level)
                    VALUES ($v, $gaeste, 'none'), ($g, $gaeste, 'none')");

        try {
            $GLOBALS['__testNutzer'] = null;   // Gast
            $frisch();
            $verborgen = board_ids_hidden_for_user();
            if (!in_array($v, $verborgen, true)) throw new RuntimeException('Verstecktes Forum steht nicht in der Filterliste');
            if (in_array($g, $verborgen, true)) throw new RuntimeException('Gesperrtes Forum wurde ebenfalls versteckt');
        } finally {
            $pdo->exec("DELETE FROM board_permissions WHERE board_id IN ($v, $g)");
            $pdo->exec("DELETE FROM boards WHERE board_id IN ($v, $g)");
            $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
        }
    },

    'Der Grund ist unterscheidbar: Passwort oder Gruppe' => function () use ($pdo, $frisch) {
        $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Grund', 93)");
        $cat = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO boards (cat_id, title, position, password_hash)
                    VALUES ($cat, 'Mit Passwort', 1, '\$2y\$10\$abcdefghijklmnopqrstuv')");
        $p = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Nur fuer Gruppe', 2)");
        $g = (int) $pdo->lastInsertId();
        $gaeste = (int) $pdo->query("SELECT group_id FROM user_groups WHERE slug='guests'")->fetchColumn();
        $pdo->exec("INSERT INTO board_permissions (board_id, group_id, level)
                    VALUES ($p, $gaeste, 'read'), ($g, $gaeste, 'none')");

        try {
            $GLOBALS['__testNutzer'] = null;
            $frisch();
            $a = board_id_zugangsgrund($p);
            $b = board_id_zugangsgrund($g);
            if ($a !== 'passwort') throw new RuntimeException("Passwort-Forum meldet '$a'");
            if ($b !== 'gruppe')   throw new RuntimeException("Gruppen-Forum meldet '$b'");
        } finally {
            $pdo->exec("DELETE FROM board_permissions WHERE board_id IN ($p, $g)");
            $pdo->exec("DELETE FROM boards WHERE board_id IN ($p, $g)");
            $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
        }
    },
];
