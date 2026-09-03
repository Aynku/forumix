<?php
/**
 * Sprachen im Adminbereich: was die Datenbank darf und was nicht.
 *
 * Der gefaehrliche Teil dieser Funktion ist nicht die Oberflaeche, sondern
 * was hineingeschrieben werden darf. Zwei Wege fuehren hier hinein — das
 * Eingabefeld und eine hochgeladene Datei — und beide muessen dasselbe
 * ablehnen.
 *
 * Braucht keine Datenbank: geprueft werden die Regeln, nicht das Speichern.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

if (!function_exists('current_user')) {
    function current_user() { return null; }
}
require_once $wurzel . '/lib/lang.php';
require_once $wurzel . '/lib/sprachen.php';

return [
    'Ohne Datenbank bleibt es bei den Dateien' => function () use ($wurzel) {
        /*
         * lib/lang.php wird auch dort geladen, wo es keine Datenbank gibt:
         * im Installer und auf der Fehlerseite, die gerade meldet, dass die
         * Datenbank weg ist. Wuerde text_overrides() dort werfen, waere die
         * Fehlerseite selbst der naechste Fehler.
         *
         * In einem EIGENEN Lauf, nicht hier: der Prueflauf hat die echte
         * Datenbank laengst geladen, und eine Attrappe kaeme zu spaet. Im
         * gemeinsamen Prozess wuerde diese Pruefung nur so tun als ob.
         */
        $code = <<<'PHP'
            define('FORUM_RUNNING', true);
            function db_fetchAll(string $sql, array $p = []): array { throw new RuntimeException('keine DB'); }
            function current_user() { return null; }
            require WURZEL . '/lib/sprachen.php';
            require WURZEL . '/lib/lang.php';
            $texte = load_language('en');
            echo (text_overrides('de') === [] && isset($texte['admin'])) ? 'ok' : 'fehl';
        PHP;

        $code = str_replace('WURZEL', var_export($wurzel, true), $code);
        $aus  = shell_exec('php -r ' . escapeshellarg($code) . ' 2>&1');

        if (trim((string) $aus) !== 'ok') {
            throw new RuntimeException('Ohne Datenbank ging es schief: ' . trim((string) $aus));
        }
    },

    'Ein verlorener Platzhalter wird abgelehnt' => function () {
        /*
         * Der eine Fehler, der eine weisse Seite macht: vsprintf wirft, wenn
         * weniger Platzhalter dastehen als Werte kommen. Wer den Text
         * aendert, sieht das nicht — der naechste Besucher schon.
         */
        $de = require dirname(__DIR__, 2) . '/lang/de.php';

        $mitPlatzhalter = null;
        foreach ($de as $schluessel => $wert) {
            if (!is_array($wert) && substr_count((string) $wert, '%s') === 1) {
                $mitPlatzhalter = $schluessel;
                break;
            }
        }

        if ($mitPlatzhalter === null) {
            throw new RuntimeException('Kein Text mit genau einem Platzhalter gefunden');
        }

        if (text_pruefen('de', $mitPlatzhalter, 'ohne Platzhalter') === null) {
            throw new RuntimeException('Ein Text ohne Platzhalter ging durch');
        }

        if (text_pruefen('de', $mitPlatzhalter, 'mit %s Platzhalter') !== null) {
            throw new RuntimeException('Ein richtiger Text wurde abgelehnt');
        }
    },

    'Ein unbekannter Schluessel wird abgelehnt' => function () {
        // Sonst sammelt die Tabelle Eintraege, die nie jemand anzeigt.
        if (text_pruefen('de', 'diesen_schluessel_gibt_es_nicht', 'x') === null) {
            throw new RuntimeException('Ein unbekannter Schluessel ging durch');
        }
    },

    'Eine PHP-Datei wird nicht angenommen' => function () {
        /*
         * DIE Stelle, an der diese Funktion gefaehrlich waere. lang/xx.php
         * wird per require ausgefuehrt — wer sie hochladen koennte, wuerde
         * Code auf dem Server ausfuehren. Angenommen wird deshalb nur JSON.
         */
        $angriff = '<?php system($_GET["x"]); ?>';
        $r = sprachdatei_lesen($angriff, 'de');

        if ($r['texte'] !== []) {
            throw new RuntimeException('Aus einer PHP-Datei wurden Texte uebernommen');
        }

        // Auch als JSON verpackter Code ist nur Text, kein Code — aber der
        // Schluessel muss trotzdem bekannt sein.
        $r2 = sprachdatei_lesen(json_encode(['<?php evil(); ?>' => 'x']), 'de');

        if ($r2['texte'] !== []) {
            throw new RuntimeException('Ein Schluessel mit PHP-Tags ging durch');
        }
    },

    'Verschachteltes JSON wird nicht angenommen' => function () {
        // Nur Zeichenketten. Alles andere waere ein Weg, etwas anderes als
        // Text in die Tabelle zu bekommen.
        $r = sprachdatei_lesen(json_encode(['admin' => ['a' => 'b']]), 'de');

        if ($r['texte'] !== []) {
            throw new RuntimeException('Eine verschachtelte Angabe ging durch');
        }
    },

    'Gueltiges JSON kommt an, Fehlerhaftes wird aufgezaehlt' => function () {
        $de = require dirname(__DIR__, 2) . '/lang/de.php';

        $einfach = null;
        foreach ($de as $schluessel => $wert) {
            if (!is_array($wert) && !str_contains((string) $wert, '%')) {
                $einfach = $schluessel;
                break;
            }
        }

        $r = sprachdatei_lesen(json_encode([
            $einfach                          => 'Mein eigener Text',
            'diesen_schluessel_gibt_es_nicht' => 'wird uebersprungen',
        ]), 'de');

        if (($r['texte'][$einfach] ?? null) !== 'Mein eigener Text') {
            throw new RuntimeException('Der gueltige Eintrag kam nicht an');
        }

        if (count($r['fehler']) !== 1) {
            throw new RuntimeException('Der ungueltige Eintrag wurde nicht gemeldet');
        }
    },

    'Ein krummer Sprachcode wird abgelehnt' => function () {
        // Der Code landet in Dateinamen und im lang-Attribut der Seite.
        foreach (['../etc', 'DE', 'd', 'viel_zu_langer_code', 'de1', ''] as $krumm) {
            if (sprache_anlegen($krumm, 'Name') === null) {
                throw new RuntimeException("Sprachcode '$krumm' ging durch");
            }
        }
    },

    'Eine Sprache aus dem Paket laesst sich nicht entfernen' => function () {
        /*
         * Die Datei bliebe ja liegen, und beim naechsten Aufruf waere die
         * Sprache wieder da — mit leeren Texten. Wer sie loswerden will,
         * schaltet sie ab.
         */
        if (sprache_entfernen('de') === null) {
            throw new RuntimeException('Deutsch liess sich entfernen');
        }
    },
];
