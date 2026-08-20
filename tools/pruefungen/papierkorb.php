<?php
/**
 * Papierkorb, Teilen, Zusammenführen (1.3.0).
 *
 * Der Papierkorb ist die Stelle, an der ein Fehler frueher nicht zu heilen war:
 * bis 1.2.x hat jedes Loeschen wirklich geloescht. Jetzt haengt daran eine
 * Kette von Zaehlern — Brett, Thema, Benutzer, letzter Beitrag — die beim
 * Loeschen herunter und beim Zurueckholen wieder hoch muss. Genau das prueft
 * dieser Lauf, samt Teilen und Zusammenfuehren.
 *
 * Laeuft als eigener Prozess gegen den Pruefstand, der vorher auf den Stand des
 * Repos gebracht wird.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel  = dirname(__DIR__, 2);
$instanz = '/root/forumix-test/instanz';
if (!is_file($instanz . '/config.php')) {
    return ['Papierkorb (uebersprungen, kein Pruefstand)' => fn() => null];
}

return [
    'Papierkorb, Teilen und Zusammenfuehren rechnen richtig' => function () use ($wurzel, $instanz) {
        exec(sprintf('rsync -a --exclude config.php --exclude .git --exclude "cache/*" --exclude "uploads/*" %s/ %s/ 2>&1',
                     escapeshellarg($wurzel), escapeshellarg($instanz)), $aus, $rc);
        if ($rc !== 0) throw new RuntimeException('Pruefstand nicht abgleichbar: ' . implode(' ', $aus));

        // Die Migration gehoert dazu: ohne die Spalten laeuft nichts.
        exec(sprintf('%s -r %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('define("FORUM_RUNNING", true); require "' . $instanz . '/bootstrap.php";'
                         . ' ob_start(); require "' . $instanz . '/install/migrations/migrate_v28.php"; ob_end_clean();')
        ), $aus2, $rc2);
        if ($rc2 !== 0) throw new RuntimeException('Migration v28 nicht ausfuehrbar: ' . implode(' ', $aus2));

        exec(sprintf('%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/hilfe/papierkorb_lauf.php'),
            escapeshellarg($instanz)), $zeilen, $rc3);
        if ($rc3 !== 0) throw new RuntimeException(implode(' ', $zeilen));
    },
];
