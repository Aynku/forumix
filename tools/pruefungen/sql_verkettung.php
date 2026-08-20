<?php
/**
 * Steht sichtbar_sql() wirklich AUSSERHALB der Abfrage? (1.3.2)
 *
 * Am 20.08.2026 nicht: in lib/reads.php landete
 *
 *     ... t.last_post_id' . sichtbar_sql('p') . '
 *
 * mitten in einem doppelt gequoteten String. Dort wird nichts verkettet — der
 * Ausdruck ging woertlich ins SQL, die Abfrage scheiterte, das leere catch
 * schluckte den Fehler, und jedes Thema galt fortan als ungelesen. Der blaue
 * Punkt ging nie wieder weg.
 *
 * Der PHP-Zerteiler beantwortet das eindeutig: taucht der Name in einem
 * String-Token auf, ist es Text und kein Aufruf.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

/* Alle Helfer, die als Zusatz an eine Abfrage gehaengt werden. */
$helfer = ['sichtbar_sql', 'board_filter_sql'];

return [
    'Abfrage-Helfer stehen nicht im String' => function () use ($wurzel, $helfer) {
        $funde = [];
        foreach (['pages', 'lib', 'admin', 'api', 'modules'] as $ordner) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$wurzel/$ordner"));
            foreach ($it as $datei) {
                if (!$datei->isFile() || $datei->getExtension() !== 'php') continue;
                $quelle = (string) file_get_contents($datei->getPathname());
                foreach (token_get_all($quelle) as $t) {
                    if (!is_array($t)) continue;
                    // T_HEREDOC gibt es nicht — der Inhalt eines Heredocs
                    // kommt ebenfalls als T_ENCAPSED_AND_WHITESPACE.
                    if (!in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE,
                                          T_INLINE_HTML], true)) continue;
                    foreach ($helfer as $h) {
                        if (str_contains($t[1], $h)) {
                            $funde[] = str_replace("$wurzel/", '', $datei->getPathname()) . ':' . $t[2] . " ($h)";
                        }
                    }
                }
            }
        }
        if ($funde) {
            throw new RuntimeException(
                "Der Helfer steht im String und wird nicht ausgeführt:\n        "
                . implode("\n        ", $funde));
        }
    },
];
