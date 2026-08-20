<?php
/**
 * 1.3.x auf einer Datenbank ohne Migration v28.
 *
 * Der Ausfall vom 20.08.2026 als Prueflauf: Spalten weg, laeuft es trotzdem?
 * Muss ganz am Ende stehen — er nimmt dem Pruefstand kurzzeitig die Spalten weg
 * und legt sie danach wieder an.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel  = dirname(__DIR__, 2);
$instanz = '/root/forumix-test/instanz';
if (!is_file($instanz . '/config.php')) {
    return ['Ohne Migration (uebersprungen, kein Pruefstand)' => fn() => null];
}

return [
    'Ohne Migration v28 laeuft das Forum weiter' => function () use ($wurzel, $instanz) {
        exec(sprintf('rsync -a --exclude config.php --exclude .git --exclude "cache/*" --exclude "uploads/*" %s/ %s/ 2>&1',
                     escapeshellarg($wurzel), escapeshellarg($instanz)), $aus, $rc);
        if ($rc !== 0) throw new RuntimeException('Pruefstand nicht abgleichbar');

        exec(sprintf('%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/hilfe/ohne_migration_lauf.php'),
            escapeshellarg($instanz)), $zeilen, $rc2);
        if ($rc2 !== 0) throw new RuntimeException(implode(' ', $zeilen));
    },
];
