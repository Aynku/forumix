<?php
/**
 * Prueflauf ohne Fremdpakete.
 *
 *   php tools/pruefen.php            alle Pruefungen
 *   php tools/pruefen.php auth       nur eine Gruppe
 *
 * Jede Datei in tools/pruefungen/ liefert ein Array
 *   'Beschreibung' => fn() => ...   (wirft bei Fehlschlag)
 *
 * Gedacht fuer Funde aus dem Audit: erst die Pruefung schreiben, die den Weg
 * nachstellt und FEHLSCHLAEGT, dann den Fund beheben, dann laeuft sie durch.
 *
 * Es wird gegen die Testdatenbank gefahren, nie gegen ein laufendes Forum —
 * dafuer setzt der Aufrufer FORUM_TEST_DB (siehe tools/pruefungen/README.md).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Nur von der Kommandozeile.');
}

define('FORUM_RUNNING', true);
define('FORUM_PRUEFLAUF', true);

$wurzel = dirname(__DIR__);
$gruppe = $argv[1] ?? '';

$dateien = glob($wurzel . '/tools/pruefungen/*.php') ?: [];
if ($gruppe !== '') {
    $dateien = array_filter($dateien, fn($d) => basename($d, '.php') === $gruppe);
    if (!$dateien) { fwrite(STDERR, "Keine Gruppe '$gruppe'.\n"); exit(2); }
}

$gut = 0; $schlecht = 0; $fehler = [];

foreach ($dateien as $datei) {
    $name = basename($datei, '.php');
    $pruefungen = require $datei;
    if (!is_array($pruefungen)) continue;

    echo "\n── $name ──\n";
    foreach ($pruefungen as $beschreibung => $pruefung) {
        try {
            $pruefung();
            echo "  ok    $beschreibung\n";
            $gut++;
        } catch (Throwable $e) {
            echo "  FEHL  $beschreibung\n";
            echo "        " . $e->getMessage() . "\n";
            $schlecht++;
            $fehler[] = "$name: $beschreibung";
        }
    }
}

echo "\n" . str_repeat('─', 50) . "\n";
echo "$gut bestanden, $schlecht fehlgeschlagen\n";
if ($fehler) {
    echo "\nFehlgeschlagen:\n";
    foreach ($fehler as $f) echo "  - $f\n";
}
exit($schlecht > 0 ? 1 : 0);
