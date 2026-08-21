<?php
/**
 * 1.5.0 auf einer Datenbank ohne Migration v30.
 *
 * Dieselbe Lage wie beim Papierkorb am 20.08.2026: Fassung ausgerollt,
 * Anpassung noch nicht gefahren. Ohne die Spalten muss jedes Recht als
 * erlaubt gelten — eine Verschaerfung, die niemand angeordnet hat, waere hier
 * genauso falsch wie ein Fehler 500.
 *
 * Der Lauf nimmt dem Pruefstand die Spalten kurz weg und legt sie danach
 * wieder an.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel  = dirname(__DIR__, 2);
$instanz = '/root/forumix-test/instanz';
if (!is_file($instanz . '/config.php')) {
    return ['Feinrechte ohne Migration (uebersprungen, kein Pruefstand)' => fn() => null];
}

return [
    'Ohne Migration v30 gilt jedes Recht als erlaubt' => function () use ($wurzel, $instanz) {
        exec(sprintf('rsync -a --exclude config.php --exclude .git --exclude "cache/*" --exclude "uploads/*" %s/ %s/ 2>&1',
                     escapeshellarg($wurzel), escapeshellarg($instanz)), $aus, $rc);
        if ($rc !== 0) throw new RuntimeException('Pruefstand nicht abgleichbar');

        exec(sprintf('%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/hilfe/feinrechte_lauf.php'),
            escapeshellarg($instanz)), $zeilen, $rc2);
        if ($rc2 !== 0) throw new RuntimeException(implode(' ', $zeilen));
    },
];
