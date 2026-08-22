<?php
/**
 * Module als eigenes Paket.
 *
 * Hier landen PHP-Dateien auf dem Server. Das ist derselbe Weg wie beim
 * Updatepaket und traegt dieselbe Verantwortung: Wer ein Paket einspielen
 * darf, fuehrt Code aus. Geprueft wird deshalb vor allem, dass die Riegel
 * halten — und dass sie nicht als Kopie danebenliegen, wo sie beim naechsten
 * Fund vergessen werden.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);

if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
if (!defined('FORUM_VERSION_CODE')) {
    preg_match("/FORUM_VERSION_CODE\s*=\s*'([^']+)'/", (string) file_get_contents($wurzel . '/bootstrap.php'), $t);
    define('FORUM_VERSION_CODE', $t[1] ?? '0');
}
require_once $wurzel . '/lib/update_package.php';
require_once $wurzel . '/lib/module_package.php';

return [
    'Der Zip-Slip-Schutz gilt auch für Modulpakete' => function () {
        // Nicht als zweite Kopie: eine Sicherheitspruefung, die zweimal
        // dasteht, wird beim naechsten Fund einmal vergessen.
        $quelle = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/module_package.php');
        if (!str_contains($quelle, 'update_validate_entry')) {
            throw new RuntimeException('Das Modulpaket bringt eine eigene Prüfung mit statt der geprüften');
        }
        foreach (['../evil.php', '/etc/passwd', 'files/../../bootstrap.php'] as $boese) {
            if (update_validate_entry($boese, ['module.json']) === null) {
                throw new RuntimeException('Durchgelassen: ' . $boese);
            }
        }
    },

    'Nur module.json darf im Wurzelverzeichnis liegen' => function () {
        if (update_validate_entry('module.json', ['module.json']) !== null) {
            throw new RuntimeException('Das eigene Manifest wurde abgelehnt');
        }
        // update.sql waere ein zweiter Weg, SQL auszufuehren. Einer genuegt.
        foreach (['update.sql', 'update.json', 'beliebig.php'] as $fremd) {
            if (update_validate_entry($fremd, ['module.json']) === null) {
                throw new RuntimeException("Im Wurzelverzeichnis erlaubt, sollte es nicht: $fremd");
            }
        }
    },

    'Ein Modul für eine neuere Forumix-Fassung wird abgewiesen' => function () {
        // Ohne das installiert sich ein Modul und faellt danach mit
        // "Call to undefined function" um — an einer Stelle, die niemand mit
        // dem Modul in Verbindung bringt.
        $fehler = modulpaket_fassung_pruefen(['requires' => ['forumix' => '99.0.0']]);
        if ($fehler === null) throw new RuntimeException('Ein zu neues Modul wurde angenommen');

        foreach ([['forumix' => '0.1.0'], []] as $verlangt) {
            if (modulpaket_fassung_pruefen(['requires' => $verlangt]) !== null) {
                throw new RuntimeException('Ein passendes Modul wurde abgewiesen');
            }
        }
    },

    'Ein angehängtes „ Beta" stört den Fassungsvergleich nicht' => function () {
        // Dieselbe Falle wie beim Updatepaket: FORUM_VERSION_CODE kann einen
        // Zusatz tragen, und version_compare macht daraus eine kleinere Zahl.
        $roh = modulpaket_fassung_pruefen(['requires' => ['forumix' => '1.0.0 Beta']]);
        if ($roh !== null) throw new RuntimeException('Zusatz in der verlangten Fassung stört: ' . $roh);
    },

    'Nur der Inhaber darf einspielen' => function () use ($wurzel) {
        $admin = (string) file_get_contents($wurzel . '/admin/index.php');
        $pos = strpos($admin, "action === 'module_upload'");
        if ($pos === false) throw new RuntimeException('Der Weg zum Einspielen fehlt');
        $block = substr($admin, $pos, 900);

        if (!str_contains($block, 'is_owner()')) {
            throw new RuntimeException('Jeder Administrator dürfte Code einspielen');
        }
        if (!str_contains($block, 'verify_csrf')) {
            throw new RuntimeException('Ohne CSRF-Prüfung');
        }
        if (!str_contains($block, 'is_uploaded_file')) {
            throw new RuntimeException('Der Pfad wird nicht als Upload geprüft');
        }
    },

    'Ein Paket ohne module.php wird abgewiesen' => function () use ($wurzel) {
        // Sonst liegt ein Verzeichnis unter modules/, das der Kern nicht als
        // Modul erkennt — und niemand versteht, warum nichts passiert.
        $quelle = (string) file_get_contents($wurzel . '/lib/module_package.php');
        if (!str_contains($quelle, 'files/module.php')) {
            throw new RuntimeException('Das Pflicht-Manifest des Moduls wird nicht verlangt');
        }
    },

    'Nur FAQ und Kontakt liegen im Kernpaket' => function () use ($wurzel) {
        // Geprueft wird das Archiv, nicht die .gitattributes: Deren Muster
        // verhalten sich anders als erwartet (git check-attr meldet
        // "unspecified", obwohl git archive den Pfad weglaesst). Was zaehlt,
        // ist das Paket beim Kunden.
        $alt = getcwd();
        chdir($wurzel);
        $tree = trim((string) shell_exec('git write-tree 2>/dev/null'));
        if ($tree === '') { chdir($alt); return null; }   // kein Repo, nichts zu pruefen

        $liste = (string) shell_exec('git archive ' . escapeshellarg($tree)
            . ' --format=tar 2>/dev/null | tar -t 2>/dev/null | grep "^modules/" | cut -d/ -f2 | sort -u');
        chdir($alt);

        $drin = array_values(array_filter(array_map('trim', explode("\n", $liste))));
        sort($drin);

        $soll = ['faq', 'kontakt'];
        if ($drin !== $soll) {
            throw new RuntimeException('Im Kernpaket liegen: ' . implode(', ', $drin)
                . ' — erwartet: ' . implode(', ', $soll)
                . '. Neue Module brauchen einen Eintrag in .gitattributes, sonst gehen sie '
                . 'doppelt raus: im Kernpaket und als eigenes Paket.');
        }
    },

    // ── Der einzige Aufruf eines fremden Servers ──────────────────────

    'Der Modulbezug ist im Auslieferungszustand aus' => function () use ($wurzel) {
        // Auf der Projektseite steht: "Forumix ruft im Auslieferungszustand
        // keinen fremden Server auf." Der Satz haelt nur, solange die Vorgabe
        // hier 0 ist.
        $quelle = (string) file_get_contents($wurzel . '/lib/module_quelle.php');
        if (!preg_match("/get_setting\(\s*'module_quelle_an'\s*,\s*'0'\s*\)/", $quelle)) {
            throw new RuntimeException('Die Vorgabe ist nicht „aus" — damit ruft ein frisch '
                . 'installiertes Forum einen fremden Server auf');
        }
    },

    'Der Abruf verrät nichts über das Forum' => function () use ($wurzel) {
        // Kein eigener User-Agent, keine Adresse, keine Fassung im Aufruf.
        // Sonst waere es genau die Rueckmeldung an den Hersteller, die es
        // nicht geben soll.
        $quelle = (string) file_get_contents($wurzel . '/lib/module_quelle.php');
        foreach (['CURLOPT_USERAGENT', 'FORUM_URL .', 'FORUM_VERSION_CODE'] as $verraeterisch) {
            // FORUM_URL darf im Namen der Zwischenspeicher-Datei vorkommen —
            // die verlaesst den Server nicht. Im Aufruf selbst nicht.
            $pos = strpos($quelle, 'function modulquelle_holen');
            $koerper = substr($quelle, $pos, 1400);
            if (str_contains($koerper, $verraeterisch)) {
                throw new RuntimeException("Im Abruf steht $verraeterisch");
            }
        }
    },

    'Nur HTTPS, keine Umleitung, feste Quelle' => function () use ($wurzel) {
        $quelle = (string) file_get_contents($wurzel . '/lib/module_quelle.php');
        if (!str_contains($quelle, "str_starts_with(\$url, 'https://')")) {
            throw new RuntimeException('Es wird nicht auf HTTPS bestanden');
        }
        if (!preg_match('/CURLOPT_FOLLOWLOCATION\s*=>\s*false/', $quelle)) {
            throw new RuntimeException('Umleitungen werden gefolgt — die können woanders hinführen');
        }
        if (!preg_match('/CURLOPT_SSL_VERIFYPEER\s*=>\s*true/', $quelle)) {
            throw new RuntimeException('Das Zertifikat wird nicht geprüft');
        }
        // Die Adresse steht fest. Eine einstellbare waere eine Einladung,
        // Code von irgendwoher zu holen.
        if (preg_match("/get_setting\(\s*'[a-z_]*url/i", $quelle)) {
            throw new RuntimeException('Die Quelladresse ist einstellbar');
        }
    },

    'Ein geladenes Paket geht durch dieselbe Prüfung wie ein hochgeladenes' => function () use ($wurzel) {
        $admin = (string) file_get_contents($wurzel . '/admin/index.php');
        $pos = strpos($admin, "action === 'module_quelle'");
        if ($pos === false) throw new RuntimeException('Der Bezugsweg fehlt');
        $block = substr($admin, $pos, 3000);

        if (!str_contains($block, 'modulpaket_pruefen')) {
            throw new RuntimeException('Geladene Pakete werden nicht geprüft — dann wäre der '
                . 'Bezug ein Weg an allen Riegeln vorbei');
        }
        if (!str_contains($block, 'is_owner()')) {
            throw new RuntimeException('Jeder Administrator dürfte Code beziehen');
        }
    },

    'Die Prüfsumme aus der Liste wird verglichen' => function () use ($wurzel) {
        $quelle = (string) file_get_contents($wurzel . '/lib/module_quelle.php');
        if (!str_contains($quelle, 'hash_equals')) {
            throw new RuntimeException('Die Prüfsumme wird nicht oder nicht sicher verglichen');
        }
    },

    'Beim Aufrufen der Modulseite wird nichts abgerufen' => function () use ($wurzel) {
        // Sonst telefoniert das Forum bei jedem Blick in den Adminbereich.
        $admin = (string) file_get_contents($wurzel . '/admin/index.php');
        $pos = strpos($admin, "case 'modules':");
        $block = substr($admin, $pos, 800);
        if (str_contains($block, 'modulquelle_liste(true)')) {
            throw new RuntimeException('Die Seite erzwingt einen frischen Abruf');
        }
    },

    'Das Bau-Werkzeug steckt nicht im Produkt' => function () use ($wurzel) {
        // Modulpakete schnuert der Hersteller, nicht der Betreiber.
        if (is_file($wurzel . '/tools/modulpaket.sh') || is_file($wurzel . '/modulpaket.sh')) {
            throw new RuntimeException('Das Bau-Werkzeug liegt im Produkt-Repo');
        }
    },
];
