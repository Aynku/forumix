<?php
/**
 * Jede Vorlage laesst sich uebersetzen — und ergibt gueltiges PHP.
 *
 * Die Vorlagen-Sprache wird nicht ausgefuehrt, sondern zu PHP uebersetzt und
 * zwischengespeichert. Ein Tippfehler darin faellt deshalb erst auf, wenn
 * jemand genau die Seite aufruft: Die Uebersetzung geht durch, das Ergebnis
 * ist kaputtes PHP, und die Seite bleibt weiss.
 *
 * Genau das ist beim Umstellen auf das Sprachpaket passiert. In einem Aufruf
 * stand ein Filter als Argument — {{ t('key', liste|length) }}. Die
 * Vorlagen-Sprache kennt das nicht, sie hat "liste" und "length" als zwei
 * Argumente uebersetzt und ein Komma zu viel erzeugt. Gemerkt habe ich es
 * erst, als ich die Vorlagen von Hand durchgerechnet habe.
 *
 * Braucht keine Datenbank.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

if (!defined('FORUM_URL')) { define('FORUM_URL', 'https://pruefstand.invalid'); }

/*
 * TPL_CACHE_DIR wird hier NICHT gesetzt. In template.php steht sie als const,
 * und eine const laesst sich nicht ueberschreiben — wer sie vorher per
 * define() anlegt, bekommt beim Laden eine Warnung ("already defined"), die
 * in PHP 9 ein Fehler wird. Gebraucht wird sie ohnehin nicht: tpl_compile()
 * uebersetzt nur, es schreibt nichts in den Zwischenspeicher.
 */

require_once $wurzel . '/lib/template.php';

return [
    'Alle Vorlagen ergeben gueltiges PHP' => function () use ($wurzel) {
        $vorlagen = glob($wurzel . '/views_new/*.tpl.html') ?: [];

        if (count($vorlagen) < 10) {
            throw new RuntimeException('Nur ' . count($vorlagen) . ' Vorlagen gefunden — stimmt der Pfad?');
        }

        $kaputt = []; 

        foreach ($vorlagen as $datei) {
            $php = tpl_compile(file_get_contents($datei));

            /*
             * Geprueft wird mit php -l in einem eigenen Lauf. Das Ergebnis
             * hier einzubinden waere keine Pruefung, sondern ein Absturz:
             * Ein Syntaxfehler laesst sich nicht abfangen.
             */
            $ergebnis = shell_exec('echo ' . escapeshellarg($php) . ' | php -l 2>&1');

            if (!str_contains((string) $ergebnis, 'No syntax errors')) {
                $meldung = trim(explode("\n", (string) $ergebnis)[0] ?? '');
                $kaputt[] = basename($datei) . ' — ' . $meldung;
            }
        }

        if ($kaputt !== []) {
            throw new RuntimeException(
                count($kaputt) . " Vorlage(n) uebersetzen zu kaputtem PHP:\n        "
                . implode("\n        ", $kaputt)
            );
        }
    },
];
