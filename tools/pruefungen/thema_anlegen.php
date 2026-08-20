<?php
/**
 * Ein Thema anlegen — der ganze Weg (1.2.0).
 *
 * Seit 1.2.0 steht das Anlegen in lib/threads.php statt in der Seite, weil
 * tools/versionsbeitrag.php denselben Weg braucht. Daran haengen Zaehler an
 * Forum und Benutzer, der letzte Beitrag an beiden Stellen, Rang, Auto-Abo.
 * Ein Fehler faellt hier nicht sofort auf, sondern erst Wochen spaeter an
 * einer Zahl, die nicht mehr stimmt.
 *
 * Laeuft als eigener Prozess gegen den Pruefstand — Begruendung im Kopf von
 * hilfe/thema_anlegen_lauf.php. Der Pruefstand wird vorher auf den Stand des
 * Repos gebracht, sonst gruent hier alter Code.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel  = dirname(__DIR__, 2);
$instanz = '/root/forumix-test/instanz';
if (!is_file($instanz . '/config.php')) {
    return ['Thema anlegen (uebersprungen, kein Pruefstand)' => fn() => null];
}

return [
    'Thema anlegen zaehlt ueberall richtig mit' => function () use ($wurzel, $instanz) {
        $abgleich = sprintf(
            'rsync -a --exclude config.php --exclude .git --exclude "cache/*" --exclude "uploads/*" %s/ %s/ 2>&1',
            escapeshellarg($wurzel), escapeshellarg($instanz)
        );
        exec($abgleich, $aus, $rc);
        if ($rc !== 0) throw new RuntimeException('Pruefstand nicht abgleichbar: ' . implode(' ', $aus));

        $lauf = sprintf('%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/hilfe/thema_anlegen_lauf.php'),
            escapeshellarg($instanz));
        exec($lauf, $zeilen, $rc);
        if ($rc !== 0) throw new RuntimeException(implode(' ', $zeilen));
    },
];
