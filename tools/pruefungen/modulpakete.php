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

    // ── Entfernen ─────────────────────────────────────────────────────

    'Entfernen räumt auch die Datenbank ab' => function () use ($wurzel) {
        // Die drei Stufen sind aktivieren, abschalten, entfernen. Wenn
        // "entfernen" die Daten stehen laesst, unterscheidet es sich vom
        // Abschalten nur noch im Plattenplatz — und niemand weiss, wie er
        // ein Modul wirklich loswird.
        $quelle = (string) file_get_contents($wurzel . '/lib/modules.php');
        $pos = strpos($quelle, 'function module_entfernen');
        if ($pos === false) throw new RuntimeException('Das Entfernen fehlt');
        $koerper = substr($quelle, $pos, 3000);

        foreach (['DROP TABLE' => 'die Tabellen des Moduls',
                  'DELETE FROM modules' => 'der Eintrag in der Modultabelle'] as $noetig => $was) {
            if (!str_contains($koerper, $noetig)) {
                throw new RuntimeException("Beim Entfernen bleibt $was liegen");
            }
        }
    },

    'Kern-Tabellen werden nie gedroppt' => function () use ($wurzel) {
        // Ein Modul, dessen install.sql "CREATE TABLE users" enthaelt — aus
        // Versehen oder mit Absicht —, darf beim Entfernen nicht die Benutzer
        // mitnehmen.
        $quelle = (string) file_get_contents($wurzel . '/lib/modules.php');
        if (!str_contains($quelle, 'function module_kern_tabellen')) {
            throw new RuntimeException('Es gibt keine Ausnahmeliste für Kern-Tabellen');
        }
        $pos = strpos($quelle, 'function module_tabellen');
        $koerper = substr($quelle, $pos, 1200);
        if (!str_contains($koerper, 'module_kern_tabellen')) {
            throw new RuntimeException('Die Ausnahmeliste wird beim Sammeln nicht angewendet');
        }

        // Die Liste kommt aus install.sql, nicht aus dem Code: eine Liste im
        // Code waere bei der naechsten neuen Kern-Tabelle veraltet, und zwar
        // unbemerkt.
        $kern = strpos($quelle, 'function module_kern_tabellen');
        if (!str_contains(substr($quelle, $kern, 900), 'install/install.sql')) {
            throw new RuntimeException('Die Ausnahmeliste steht fest im Code statt aus install.sql zu kommen');
        }
    },

    'Die Tabellennamen werden vor dem Löschen der Dateien geholt' => function () use ($wurzel) {
        // Sie stehen in der install.sql des Moduls — nach dem Loeschen der
        // Dateien gibt es sie nicht mehr, und die Tabellen blieben fuer immer.
        $quelle = (string) file_get_contents($wurzel . '/lib/modules.php');
        $pos = strpos($quelle, 'function module_entfernen');
        $koerper = substr($quelle, $pos, 3000);

        $holen   = strpos($koerper, 'module_tabellen($code)');
        $loeschen = strpos($koerper, 'module_verzeichnis_leeren($ziel)');
        if ($holen === false || $loeschen === false || $holen > $loeschen) {
            throw new RuntimeException('Die Tabellennamen werden nach dem Löschen der install.sql '
                . 'gesucht — dann bleiben die Tabellen für immer stehen');
        }
    },

    'Die Rückfrage nennt die Tabellen beim Namen' => function () use ($wurzel) {
        // "Alle Daten gehen verloren" liest sich wie eine Floskel und wird
        // weggeklickt. "shoutbox_messages wird gelöscht" nicht.
        $view = (string) file_get_contents($wurzel . '/views/admin/index.php');
        $pos = strpos($view, 'module_entfernen');
        if ($pos === false) throw new RuntimeException('Kein Entfernen-Knopf');
        $block = substr($view, max(0, $pos - 1200), 2000);

        if (!str_contains($block, 'module_tabellen(')) {
            throw new RuntimeException('Die Rückfrage zählt die Tabellen nicht auf');
        }
        if (!str_contains($block, 'nicht rückgängig')) {
            throw new RuntimeException('Die Rückfrage sagt nicht, dass es endgültig ist');
        }
    },

    'Ein aktives Modul wird nicht entfernt' => function () use ($wurzel) {
        // Sonst verschwinden mitten im Betrieb die Dateien unter einer Seite,
        // die gerade jemand offen hat.
        $quelle = (string) file_get_contents($wurzel . '/lib/modules.php');
        $pos = strpos($quelle, 'function module_entfernen');
        $koerper = substr($quelle, $pos, 2200);

        if (!str_contains($koerper, 'noch aktiv')) {
            throw new RuntimeException('Ein laufendes Modul lässt sich entfernen');
        }
        // Und nicht ueber den Anfrage-Cache: der merkt sich seine Antwort.
        // Gesucht wird nur in Code-Zeilen — im Kommentar darueber steht der
        // Name absichtlich, als Begruendung, warum er nicht benutzt wird.
        foreach (explode("\n", $koerper) as $zeile) {
            $blank = ltrim($zeile);
            if ($blank === '' || str_starts_with($blank, '//') || str_starts_with($blank, '*')) continue;
            if (str_contains($blank, 'module_enabled(')) {
                throw new RuntimeException('Der Aktiv-Zustand kommt aus dem Anfrage-Cache — '
                    . 'wer im selben Aufruf abschaltet und entfernt, wird abgewiesen');
            }
        }
    },

    'Der Pfad wird gegen Ausbrechen geprüft' => function () use ($wurzel) {
        $quelle = (string) file_get_contents($wurzel . '/lib/modules.php');
        $pos = strpos($quelle, 'function module_entfernen');
        $koerper = substr($quelle, $pos, 2200);

        if (!str_contains($koerper, 'realpath')) {
            throw new RuntimeException('Der Zielpfad wird nicht aufgelöst');
        }
        if (!str_contains($koerper, 'str_starts_with')) {
            throw new RuntimeException('Es wird nicht geprüft, ob der Pfad im Modulverzeichnis bleibt');
        }
        // Symlinks entfernen, nicht verfolgen.
        $leeren = strpos($quelle, 'function module_verzeichnis_leeren');
        if ($leeren === false || !str_contains(substr($quelle, $leeren, 900), 'is_link')) {
            throw new RuntimeException('Symlinks werden verfolgt statt entfernt');
        }
    },

    'Nur der Inhaber darf entfernen' => function () use ($wurzel) {
        $admin = (string) file_get_contents($wurzel . '/admin/index.php');
        $pos = strpos($admin, "action === 'module_entfernen'");
        if ($pos === false) throw new RuntimeException('Der Weg zum Entfernen fehlt');
        $block = substr($admin, $pos, 900);

        foreach (['is_owner()', 'verify_csrf'] as $riegel) {
            if (!str_contains($block, $riegel)) {
                throw new RuntimeException("Riegel fehlt: $riegel");
            }
        }
        // Ueber POST, nicht ueber einen Link: ein Loeschen per GET fuehrt ein
        // Vorschaubild oder ein Sicherheitswerkzeug aus.
        if (!str_contains($block, "REQUEST_METHOD'] === 'POST'")) {
            throw new RuntimeException('Entfernen läuft über GET');
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
