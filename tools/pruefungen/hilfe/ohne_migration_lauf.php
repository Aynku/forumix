<?php
/**
 * Laeuft das Forum, wenn die Papierkorb-Spalten fehlen?
 *
 * Am 20.08.2026 nicht: 1.3.0 war ausgerollt, Migration v28 noch nicht gefahren,
 * und weil rund siebzig Abfragen nach `deleted_at` fragten, antwortete jede
 * Seite mit 500. Seit 1.3.1 gibt sichtbar_sql() in diesem Fall einen leeren
 * Zusatz zurueck. Dieser Lauf nimmt die Spalten weg, ruft die Wege auf, die
 * damals gescheitert sind, und legt sie danach wieder an.
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

$pdo->exec('ALTER TABLE posts   DROP COLUMN deleted_at, DROP COLUMN deleted_by, DROP COLUMN deleted_with_thread');
$pdo->exec('ALTER TABLE threads DROP COLUMN deleted_at, DROP COLUMN deleted_by');

try {
    if (sichtbar_sql('p') !== '') $fehler[] = 'sichtbar_sql() filtert, obwohl es die Spalten nicht gibt';
    if (papierkorb_bereit())      $fehler[] = 'papierkorb_bereit() meldet bereit ohne Spalten';

    // Genau die Wege, die damals 500 geliefert haben.
    $wege = [
        'Beitragszahl'   => fn() => db_count('SELECT COUNT(*) FROM posts WHERE 1 = 1' . sichtbar_sql()),
        'Brettzaehler'   => fn() => board_zaehler_neu(1),
        'Warteschlange'  => fn() => pending_threads(),
        'Dashboard'      => fn() => dashboard_overview(1),
        'Papierkorbliste'=> fn() => papierkorb_themen(),
    ];
    foreach ($wege as $name => $weg) {
        try { $weg(); } catch (Throwable $e) { $fehler[] = "$name: " . $e->getMessage(); }
    }

    // Und die schreibenden Wege weisen ab, statt in einen Datenbankfehler zu laufen.
    if (beitrag_in_papierkorb(1, 1) !== false) $fehler[] = 'Loeschen meldet Erfolg ohne Spalten';
    if (thema_zurueckholen(1) !== false)       $fehler[] = 'Zurueckholen meldet Erfolg ohne Spalten';
} finally {
    ob_start();
    require $instanz . '/install/migrations/migrate_v28.php';
    ob_end_clean();
}

$da = (bool) db_fetch("SHOW COLUMNS FROM posts LIKE 'deleted_at'");
if (!$da) $fehler[] = 'Die Spalten wurden nicht wieder angelegt';

if ($fehler) { fwrite(STDERR, implode('; ', $fehler) . "\n"); exit(1); }
echo "ok\n";
