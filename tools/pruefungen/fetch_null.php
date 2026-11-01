<?php
/**
 * Funktionen mit Rueckgabetyp ?array geben db_fetch() nicht roh zurueck.
 *
 * Anlass: Am 01.11.2026 lieferte reset.php mit einem alten oder falschen
 * Link einen 500er. verify_reset_token() verspricht ?array, gab aber das
 * Ergebnis von db_fetch() unveraendert weiter — und das ist false, wenn es
 * keine Zeile gibt. PHP wirft dann einen TypeError statt "Link ungueltig"
 * zu zeigen. verify_email_token() hatte denselben Fehler: ein abgelaufener
 * Bestaetigungslink aus der Registrierungsmail endete ebenso.
 *
 * Braucht keine Datenbank: gelesen wird der Quelltext unter lib/.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

return [
    'Kein ?array gibt db_fetch() ungeprueft zurueck' => function () use ($wurzel) {
        $funde = [];
        foreach (glob($wurzel . '/lib/*.php') as $datei) {
            $code = (string) file_get_contents($datei);
            // Kopf bis zur ersten schliessenden Klammer am Zeilenanfang: das
            // ist der Rumpf, so wie die Dateien hier eingerueckt sind.
            preg_match_all('/function\s+(\w+)\s*\([^)]*\)\s*:\s*\?array\s*\{(.*?)\n\}/s', $code, $treffer, PREG_SET_ORDER);
            foreach ($treffer as [, $name, $rumpf]) {
                if (!preg_match_all('/return\s+db_fetch\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)\s*([^;]*);/s', $rumpf, $rueck)) continue;
                foreach ($rueck[1] as $rest) {
                    if (!str_contains($rest, '?:') && !str_contains($rest, '??')) {
                        $funde[] = basename($datei) . ': ' . $name . '()';
                    }
                }
            }
        }
        if ($funde) {
            throw new RuntimeException('db_fetch() kann false liefern, der Typ verlangt ?array: ' . implode(', ', $funde));
        }
    },
];
