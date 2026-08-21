<?php
/**
 * Laeuft das Forum, wenn die Feinrechte-Spalten fehlen?
 *
 * Der Lauf braucht einen eigenen Prozess, weil feinrechte_bereit() sich die
 * Antwort merkt — im Elternprozess stuende sie schon auf "ja".
 *
 * Geprueft wird beides: dass nichts in einen Datenbankfehler laeuft, und dass
 * die Antwort "erlaubt" lautet statt "verboten".
 */
declare(strict_types=1);

$instanz = $argv[1] ?? '';
if ($instanz === '' || !is_file($instanz . '/bootstrap.php')) {
    fwrite(STDERR, "Kein Pruefstand unter '$instanz'.\n"); exit(2);
}
define('FORUM_RUNNING', true);
require $instanz . '/bootstrap.php';

$pdo = DB::get();
$fehler = [];

foreach (array_values(BRETT_FEINRECHTE) as $spalte) {
    try { $pdo->exec("ALTER TABLE board_permissions DROP COLUMN `$spalte`"); } catch (Throwable $e) {}
}

try {
    if (feinrechte_bereit()) $fehler[] = 'feinrechte_bereit() meldet bereit ohne Spalten';

    // Ein Forum, in dem die Angemeldeten schreiben duerfen.
    $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Ohne-v30', 94)");
    $cat = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Ohne v30', 1)");
    $brett = (int) $pdo->lastInsertId();
    $mit = (int) $pdo->query("SELECT group_id FROM user_groups WHERE slug='members'")->fetchColumn();
    $pdo->exec("INSERT INTO users (username, password, email)
                VALUES ('pruef_ohne_v30', 'x', 'ohnev30@example.invalid')");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO board_permissions (board_id, group_id, level)
                VALUES ($brett, $mit, 'write')");

    try {
        foreach (array_keys(BRETT_FEINRECHTE) as $recht) {
            try {
                if (!board_darf_for_user($brett, $uid, $recht)) {
                    $fehler[] = "board_darf_for_user verbietet '$recht' ohne Spalten";
                }
            } catch (Throwable $e) {
                $fehler[] = "board_darf_for_user('$recht'): " . $e->getMessage();
            }
        }

        // Und die Verwaltung darf nicht in einen Datenbankfehler laufen.
        try {
            board_permissions_set($brett, [$mit => 'write']);
        } catch (Throwable $e) {
            $fehler[] = 'board_permissions_set: ' . $e->getMessage();
        }
        try {
            if (board_feinrechte_map($brett) !== []) {
                $fehler[] = 'board_feinrechte_map liefert Eintraege ohne Spalten';
            }
        } catch (Throwable $e) {
            $fehler[] = 'board_feinrechte_map: ' . $e->getMessage();
        }
    } finally {
        $pdo->exec("DELETE FROM board_permissions WHERE board_id = $brett");
        $pdo->exec("DELETE FROM users WHERE user_id = $uid");
        $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
        $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
    }
} finally {
    ob_start();
    require $instanz . '/install/migrations/migrate_v30.php';
    ob_end_clean();
}

if (!$pdo->query("SHOW COLUMNS FROM board_permissions LIKE 'can_thread'")->fetch()) {
    $fehler[] = 'Die Spalten wurden nicht wieder angelegt';
}

if ($fehler) { fwrite(STDERR, implode('; ', $fehler) . "\n"); exit(1); }
echo "ok\n";
