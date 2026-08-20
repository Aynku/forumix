<?php
/**
 * Volltextsuche (1.4.0).
 *
 * Geprueft wird beides: dass der Index gefragt wird, wenn er da ist — und dass
 * die Suche weiter findet, wenn er fehlt. Der zweite Teil ist der wichtigere:
 * am 20.08.2026 hat eine fehlende Migration ein ganzes Forum auf 500 gelegt,
 * weil der Code eine Spalte voraussetzte, die es noch nicht gab.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel  = dirname(__DIR__, 2);
$instanz = '/root/forumix-test/instanz';
if (!is_file($instanz . '/config.php')) {
    return ['Suche (uebersprungen, kein Pruefstand)' => fn() => null];
}

return [
    'Volltextsuche findet, sortiert und faellt sauber zurueck' => function () use ($wurzel, $instanz) {
        exec(sprintf('rsync -a --exclude config.php --exclude .git --exclude "cache/*" --exclude "uploads/*" %s/ %s/ 2>&1',
                     escapeshellarg($wurzel), escapeshellarg($instanz)), $aus, $rc);
        if ($rc !== 0) throw new RuntimeException('Pruefstand nicht abgleichbar');

        // Zwei Laeufe, weil sich der Schalter seine Antwort je Prozess merkt:
        // einmal mit Index, einmal ohne. Der zweite legt ihn hinterher wieder an.
        foreach (['mit', 'ohne'] as $modus) {
            $zeilen = [];
            exec(sprintf('%s %s %s %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(__DIR__ . '/hilfe/suche_lauf.php'),
                escapeshellarg($instanz),
                escapeshellarg($modus)), $zeilen, $rc2);
            if ($rc2 !== 0) throw new RuntimeException("[$modus] " . implode(' ', $zeilen));
        }
    },
];
