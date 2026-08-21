<?php
/**
 * Die KI-Schreibhilfe (modules/ki).
 *
 * Zwei Dinge sind hier gefaehrlich und werden deshalb geprueft:
 *
 * 1. Die Gegenueberstellung. Sie ist der einzige Schutz davor, dass beim
 *    Glattbuegeln eine Verneinung verschwindet. Wenn sie eine Aenderung
 *    nicht zeigt, ist die ganze Vorschau wertlos — sie sieht dann nur so
 *    aus, als haette jemand hingesehen.
 *
 * 2. Der API-Schluessel. An ihm haengt eine Rechnung. Er darf nicht im
 *    Klartext in der Datenbank landen, wo eine Sicherung ihn mitnimmt.
 *
 * Der Rest sind die Regeln darum: was gekennzeichnet wird, wer darf, und
 * dass ein Befehl ohne Zielsprache nicht ins Blaue uebersetzt.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);
$modul  = $wurzel . '/modules/ki';

if (!is_dir($modul)) {
    return ['KI-Schreibhilfe (uebersprungen, Modul fehlt)' => fn() => null];
}

if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once $modul . '/lib/anbieter.php';
require_once $modul . '/lib/befehle.php';
require_once $modul . '/lib/schutz.php';

/**
 * Ruft eine Funktion aus ki.js in node auf.
 *
 * ki.js ist eine gekapselte Funktion — von aussen kommt man an nichts heran.
 * Statt den Browser zu starten, werden die benoetigten Teile aus dem Quelltext
 * geschnitten und einzeln ausgewertet. Geprueft wird damit derselbe Code, der
 * ausgeliefert wird, nicht eine Nachbildung.
 */
$inJs = function (array $teile, string $aufruf, array $argumente) use ($modul) {
    $skript = <<<'JS'
const fs = require('fs');
const quelle = fs.readFileSync(process.argv[2], 'utf8');
const stueck = (name) => {
  const kopf = ['function ' + name, 'var ' + name + ' =' ];
  let start = -1;
  for (const k of kopf) { const i = quelle.indexOf(k); if (i >= 0) { start = i; break; } }
  if (start < 0) throw new Error('nicht gefunden: ' + name);
  // Der erste Oeffner nach dem Namen entscheidet, ob es eine Funktion ({)
  // oder eine Liste ([) ist.
  const kandidaten = ['{', '['].map(c => { const i = quelle.indexOf(c, start); return i < 0 ? Infinity : i; });
  const oeffner = Math.min(...kandidaten);
  if (!isFinite(oeffner)) throw new Error('kein Block bei ' + name);
  const auf = quelle[oeffner], zu = auf === '{' ? '}' : ']';
  let tiefe = 0;
  for (let j = oeffner; j < quelle.length; j++) {
    if (quelle[j] === auf) tiefe++;
    else if (quelle[j] === zu) { tiefe--; if (tiefe === 0) return quelle.slice(start, j + 1); }
  }
  throw new Error('unvollstaendig: ' + name);
};
const teile = JSON.parse(process.argv[3]);
const code = teile.map(stueck).join(String.fromCharCode(10))
           + String.fromCharCode(10) + 'var DIFF_GRENZE = 1200;' + String.fromCharCode(10);
const f = new Function(code + 'return (' + process.argv[4] + ')(...JSON.parse(process.argv[5]));');
process.stdout.write(JSON.stringify(f()));
JS;
    $tmp = sys_get_temp_dir() . '/ki_js_pruef.js';
    file_put_contents($tmp, $skript);
    $cmd = 'node ' . escapeshellarg($tmp) . ' ' . escapeshellarg($modul . '/assets/ki.js') . ' '
         . escapeshellarg(json_encode($teile)) . ' ' . escapeshellarg($aufruf) . ' '
         . escapeshellarg(json_encode($argumente)) . ' 2>&1';
    $aus = shell_exec($cmd);
    @unlink($tmp);
    $daten = json_decode((string) $aus, true);
    return $daten;
};

/** Der Wortvergleich. */
$vergleich = function (string $alt, string $neu) use ($inJs): ?array {
    $d = $inJs(['inWoerter', 'vergleiche'], 'vergleiche', [$alt, $neu]);
    return is_array($d) ? $d : null;
};

/** Die Suche nach schutzwuerdigen Angaben. */
$suche = function (string $text) use ($inJs): ?array {
    $d = $inJs(['luhn', 'ipPlausibel', 'telefonPlausibel', 'MUSTER',
                'geschuetzteBereiche', 'liegtIn', 'funde'], 'funde', [$text]);
    return is_array($d) ? $d : null;
};

/** Sammelt die Woerter eines Typs aus dem Vergleichsergebnis. */
$woerter = function (?array $teile, string $typ): string {
    if ($teile === null) return '';
    $out = '';
    foreach ($teile as $t) if (($t['t'] ?? '') === $typ) $out .= $t['w'];
    return trim($out);
};

return [
    // ── Die Gegenueberstellung ────────────────────────────────────────

    'Eine gestrichene Verneinung wird als entfernt gezeigt' => function () use ($vergleich, $woerter) {
        if (!trim((string) shell_exec('command -v node'))) return null;   // ohne node nicht pruefbar

        $teile = $vergleich(
            'Das Modul läuft nicht unter PHP 8.0.',
            'Das Modul läuft unter PHP 8.0.'
        );
        if ($teile === null) throw new RuntimeException('Vergleich lieferte kein Ergebnis');

        $weg = $woerter($teile, '-');
        if (!str_contains($weg, 'nicht')) {
            throw new RuntimeException('"nicht" verschwand, ohne als entfernt markiert zu werden — '
                . 'als entfernt gezeigt wurde: ' . var_export($weg, true));
        }
    },

    'Unveränderter Text erzeugt keine Markierungen' => function () use ($vergleich, $woerter) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        $satz  = 'Ein Satz, an dem sich nichts ändert.';
        $teile = $vergleich($satz, $satz);
        if ($teile === null) throw new RuntimeException('Vergleich lieferte kein Ergebnis');

        if ($woerter($teile, '-') !== '' || $woerter($teile, '+') !== '') {
            throw new RuntimeException('Gleicher Text wurde als geändert gezeigt');
        }
    },

    'Ein hinzugekommener Satz wird als neu gezeigt' => function () use ($vergleich, $woerter) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        $teile = $vergleich('Erster Satz.', 'Erster Satz. Zweiter Satz.');
        if ($teile === null) throw new RuntimeException('Vergleich lieferte kein Ergebnis');

        $neu = $woerter($teile, '+');
        if (!str_contains($neu, 'Zweiter')) {
            throw new RuntimeException('Neuer Text wurde nicht als neu gezeigt: ' . var_export($neu, true));
        }
    },

    'Sehr lange Texte werden nicht verglichen, sondern als solche gemeldet' => function () use ($vergleich) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        $lang  = str_repeat('Wort ', 1500);
        $teile = $vergleich($lang, $lang . 'Ende');
        // null ist hier die richtige Antwort: die Oberflaeche zeigt dann beide
        // Fassungen ohne Hervorhebung und sagt es dazu.
        if ($teile !== null) {
            throw new RuntimeException('Der Vergleich lief trotz Überlänge — das blockiert den Browser');
        }
    },

    // ── Zitate und Quelltext ──────────────────────────────────────────

    'Ein Zitat wird gar nicht erst mitgeschickt' => function () {
        [$ohne, $bloecke] = ki_bloecke_heraus('Text [quote]fremde Worte[/quote] mehr');
        if (count($bloecke) !== 1)                  throw new RuntimeException('Zitat nicht erkannt');
        if (str_contains($ohne, 'fremde Worte'))    throw new RuntimeException('Das Zitat ginge mit hinaus');
    },

    'Verschachtelte Zitate zählen als einer' => function () {
        $text = 'A [quote=Max][quote]innen[/quote] aussen[/quote] B';
        [$ohne, $bloecke] = ki_bloecke_heraus($text);
        if (count($bloecke) !== 1) {
            throw new RuntimeException('Verschachtelung falsch gezählt: ' . count($bloecke)
                . ' Blöcke statt 1 — der äußere Block wäre zerrissen');
        }
        [$zurueck] = ki_bloecke_zurueck($ohne, $bloecke);
        if ($zurueck !== $text) throw new RuntimeException('Rücktausch ergab nicht das Original');
    },

    'Quelltext bleibt Zeichen für Zeichen erhalten' => function () {
        $code = "[code]PassivePorts 49152-65534\n  # nicht anfassen\n[/code]";
        $text = "Hier: $code — und Text danach.";
        [$ohne, $bloecke] = ki_bloecke_heraus($text);
        [$zurueck] = ki_bloecke_zurueck($ohne, $bloecke);
        if ($zurueck !== $text) throw new RuntimeException('Quelltext kam verändert zurück');
    },

    'Ein offenes Tag wird nicht als Block gelesen' => function () {
        $text = 'Ein [quote]Zitat ohne Ende';
        [$ohne, $bloecke] = ki_bloecke_heraus($text);
        if ($bloecke)      throw new RuntimeException('Offenes Tag wurde als Block genommen');
        if ($ohne !== $text) throw new RuntimeException('Text wurde dabei verändert');
    },

    'Ein Beitrag aus lauter Zitaten hat nichts zu bearbeiten' => function () {
        [$ohne] = ki_bloecke_heraus('[quote]nur das[/quote]');
        if (ki_hat_eigenen_text($ohne)) {
            throw new RuntimeException('Sonst ginge eine Anfrage hinaus, die nur aus einer Marke besteht — '
                . 'und das Modell denkt sich etwas dazu aus');
        }
    },

    'Ein Zitat am Anfang wird abgetrennt statt durchgereicht' => function () {
        // Gemessen: das Modell liess die Marke eines fuehrenden Zitats weg,
        // und der Auffang haengte das Zitat hinten an. In einem Forum steht
        // das Zitat fast immer vorn — also gar nicht erst mitschicken.
        $text = "[quote=Max]fremde Worte[/quote]\n\nMeine Antwort darauf.";
        [$vorspann, $mitte, $nachspann] = ki_raender_abtrennen($text);
        [$ohne, $bloecke] = ki_bloecke_heraus($mitte);

        if ($bloecke)                              throw new RuntimeException('Es wird noch eine Marke gesendet');
        if (!str_contains($vorspann, 'fremde'))    throw new RuntimeException('Das Zitat wurde nicht abgetrennt');
        if (str_contains($ohne, 'fremde'))         throw new RuntimeException('Das Zitat ginge trotzdem hinaus');
        if ($vorspann . $ohne . $nachspann !== $text) throw new RuntimeException('Zusammensetzen ergab nicht das Original');
    },

    'Auch am Ende, und beides zugleich' => function () {
        foreach ([
            "Meine Frage.\n\n[quote]Beleg[/quote]",
            "[quote]a[/quote] Mitte [quote]b[/quote]",
            "[quote]a[/quote][code]x();[/code] Text",
        ] as $text) {
            [$v, $m, $h] = ki_raender_abtrennen($text);
            [$ohne, $bloecke] = ki_bloecke_heraus($m);
            if ($bloecke) throw new RuntimeException('Randblock wurde zur Marke: ' . $text);
            [$zurueck] = ki_bloecke_zurueck($ohne, $bloecke);
            if ($v . $zurueck . $h !== $text) {
                throw new RuntimeException('Zusammensetzen ergab nicht das Original: ' . $text);
            }
        }
    },

    'Ein Zitat mittendrin braucht weiterhin eine Marke' => function () {
        [$v, $m, $h] = ki_raender_abtrennen('Vorher [quote]mittendrin[/quote] nachher');
        [$ohne, $bloecke] = ki_bloecke_heraus($m);
        if (count($bloecke) !== 1) {
            throw new RuntimeException('Dort ginge der Text sonst ohne Zusammenhang hinaus');
        }
    },

    'Ein verschluckter Block geht nicht verloren' => function () {
        $bloecke = ['[quote]wichtig[/quote]'];
        // Das Modell hat die Marke unterschlagen.
        [$text, $fehlend] = ki_bloecke_zurueck('Antwort ohne Marke.', $bloecke);
        if ($fehlend !== 1)                       throw new RuntimeException('Das Verschlucken wurde nicht bemerkt');
        if (!str_contains($text, 'wichtig'))      throw new RuntimeException('Das Zitat ist verschwunden');
    },

    // ── Schutzwürdige Angaben ─────────────────────────────────────────

    'E-Mail, IBAN und Zugangsschlüssel werden gefunden' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        $text = 'Schreib an hans.meier@example.org, IBAN DE89 3704 0044 0532 0130 00, '
              . 'Schlüssel sk-abcdefghijklmnop12345.';
        $f = $suche($text);
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');

        $arten = array_column($f, 'art');
        foreach (['E-Mail-Adresse', 'IBAN', 'Zugangsschlüssel'] as $soll) {
            if (!in_array($soll, $arten, true)) {
                throw new RuntimeException("$soll wurde nicht gefunden (gefunden: "
                    . implode(', ', $arten) . ')');
            }
        }
    },

    'Eine IBAN wird vollständig erfasst' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        // Eine deutsche IBAN hat 22 Stellen und endet damit auf ein Paar,
        // nicht auf eine Vierergruppe. Wurde nur bis dorthin erkannt, blieben
        // die letzten Ziffern beim Schwaerzen im Text stehen.
        $f = $suche('Konto DE89 3704 0044 0532 0130 00 bitte.');
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        foreach ($f as $fund) {
            if ($fund['art'] !== 'IBAN') continue;
            if (!str_ends_with($fund['wert'], '00')) {
                throw new RuntimeException('IBAN nur teilweise erfasst: ' . $fund['wert']);
            }
            return;
        }
        throw new RuntimeException('IBAN gar nicht gefunden');
    },

    'Eine beliebige lange Zahl ist keine Kartennummer' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        // Ohne Prüfziffer wäre jede Bestellnummer ein Fund, und die Warnung
        // würde nach dreimal Wegklicken nicht mehr gelesen.
        $f = $suche('Meine Bestellnummer ist 1234567890123456 gewesen.');
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        foreach ($f as $fund) {
            if ($fund['art'] === 'Kartennummer') {
                throw new RuntimeException('Falschmeldung: ' . $fund['wert']);
            }
        }
    },

    'Eine echte Kartennummer wird gefunden' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        // Testnummer der Kartenanbieter, besteht die Prüfziffer.
        $f = $suche('Karte 4111 1111 1111 1111 abgelehnt.');
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        if (!in_array('Kartennummer', array_column($f, 'art'), true)) {
            throw new RuntimeException('Eine gültige Kartennummer wurde übersehen');
        }
    },

    'Was im Codeblock steht, wird nicht gemeldet' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        // Am 21.08.2026 im laufenden Forum aufgeschlagen: "PassivePorts 49152
        // 50000" aus einem Codeblock wurde als Telefonnummer gemeldet, die
        // Beispiel-IP daneben als IP-Adresse. Beides geht ohnehin nie hinaus —
        // Codebloecke trennt der Server ab. Eine Warnung vor etwas, das nicht
        // passiert, wird weggeklickt, und beim naechsten Mal die echte mit.
        $text = "Meine Config:\n[code]\nPassivePorts 49152 50000\n"
              . "MasqueradeAddress 192.168.178.42\n[/code]\nSo sieht das aus.";
        $f = $suche($text);
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        if ($f) {
            throw new RuntimeException('Falschmeldung aus dem Codeblock: '
                . json_encode(array_column($f, 'wert'), JSON_UNESCAPED_UNICODE));
        }
    },

    'Was im Zitat steht, wird auch nicht gemeldet' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        $f = $suche('[quote=Max]Schreib an max@example.org[/quote] Mach ich.');
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        if ($f) throw new RuntimeException('Falschmeldung aus dem Zitat');
    },

    'Dieselbe Angabe außerhalb wird sehr wohl gemeldet' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        // Die Gegenprobe: sonst waere die Pruefung oben auch dann gruen, wenn
        // die Suche gar nichts mehr faende.
        $f = $suche('[quote]egal[/quote] Schreib an max@example.org bitte.');
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        if (!in_array('E-Mail-Adresse', array_column($f, 'art'), true)) {
            throw new RuntimeException('Die Adresse außerhalb des Zitats wurde übersehen');
        }
    },

    'Eine Portangabe ist keine Telefonnummer' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        // Auch im Fliesstext: eine Nummer faengt mit 0 oder + an.
        foreach (['Der Bereich 49152 50000 ist frei.',
                  'Bestellung 1234567890 kam an.',
                  'Wir hatten 2019 2020 und 2021 dasselbe.'] as $text) {
            $f = $suche($text);
            if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
            foreach ($f as $fund) {
                if ($fund['art'] === 'Telefonnummer') {
                    throw new RuntimeException('Falschmeldung in ' . var_export($text, true)
                        . ': ' . $fund['wert']);
                }
            }
        }
    },

    'Eine echte Nummer wird weiterhin gefunden' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        foreach (['Ruf an unter 0151 23456789.', 'Oder +49 151 23456789.'] as $text) {
            $f = $suche($text);
            if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
            if (!in_array('Telefonnummer', array_column($f, 'art'), true)) {
                throw new RuntimeException('Nummer übersehen in ' . var_export($text, true));
            }
        }
    },

    'Harmloser Text erzeugt keine Warnung' => function () use ($suche) {
        if (!trim((string) shell_exec('command -v node'))) return null;

        $f = $suche('Das Forum läuft seit 2026 und hat 3.000 Beiträge. Version 1.7.0 ist raus.');
        if ($f === null) throw new RuntimeException('Suche lieferte kein Ergebnis');
        if ($f) {
            throw new RuntimeException('Falschmeldung bei harmlosem Text: '
                . json_encode(array_column($f, 'wert'), JSON_UNESCAPED_UNICODE));
        }
    },

    'Die Marken werden dem Modell erklärt' => function () use ($modul) {
        // Ohne den Zusatz im Systemtext raeumt das Modell die Marken beim
        // Umformulieren weg — und dann fehlt das Zitat an seiner Stelle.
        $quelle = (string) file_get_contents($modul . '/api.php');
        if (!str_contains($quelle, 'BLOCK0') || !str_contains($quelle, 'DATEN0')) {
            throw new RuntimeException('Die Anweisung zu den Marken fehlt im Endpunkt');
        }
    },

    // ── Kennzeichnung ─────────────────────────────────────────────────

    'Rechtschreibung kennzeichnet nicht, Zusammenfassen schon' => function () {
        if (ki_kennzeichnet('rechtschreibung', [])) {
            throw new RuntimeException('Eine Rechtschreibkorrektur macht niemanden zum KI-Autor');
        }
        foreach (['kuerzen', 'zusammenfassen', 'uebersetzen'] as $b) {
            if (!ki_kennzeichnet($b, [])) {
                throw new RuntimeException("$b ändert den Inhalt und muss gekennzeichnet werden");
            }
        }
    },

    'Der Betreiber kann die Kennzeichnung je Befehl verschieben' => function () {
        $cfg = ['kennzeichnet' => ['rechtschreibung' => true, 'kuerzen' => false]];
        if (!ki_kennzeichnet('rechtschreibung', $cfg)) throw new RuntimeException('Einschalten wirkte nicht');
        if (ki_kennzeichnet('kuerzen', $cfg))          throw new RuntimeException('Ausschalten wirkte nicht');
    },

    'Ein unbekannter Befehl wird im Zweifel gekennzeichnet' => function () {
        if (!ki_kennzeichnet('gibtesnicht', [])) {
            throw new RuntimeException('Unbekanntes muss auf der sicheren Seite landen');
        }
    },

    // ── Platz im Adminbereich ─────────────────────────────────────────

    'Verwaltungsseiten stehen nicht in der Hauptnavigation' => function () use ($wurzel) {
        // Ein Modul, das nur die Verwaltung angeht, hat im Menue zwischen
        // "Mitglieder" und "Suchen" nichts zu suchen. Der Haken 'admin_nav'
        // im Adminbereich ist der richtige Platz — 'menu' im Manifest waere
        // der bequeme.
        foreach (glob($wurzel . '/modules/*/module.php') as $datei) {
            $code = basename(dirname($datei));
            $inhalt = (string) file_get_contents($datei);
            if (!preg_match("/'menu'\s*=>/", $inhalt)) continue;
            if (preg_match("/'admin'\s*=>\s*true/", $inhalt)) {
                throw new RuntimeException("Modul $code hängt eine Verwaltungsseite in die "
                    . "Hauptnavigation — gehört nach hooks/admin_nav.php");
            }
        }
    },

    'Der Adminbereich hat den Einhängepunkt' => function () use ($wurzel) {
        $inhalt = (string) file_get_contents($wurzel . '/views/admin/index.php');
        if (!str_contains($inhalt, "render_module_hook('admin_nav'")) {
            throw new RuntimeException('Ohne den Haken sind die Modulseiten nicht mehr erreichbar');
        }
    },

    'Jeder admin_nav-Haken prüft selbst die Rechte' => function () use ($wurzel) {
        // Der Haken laeuft im Adminbereich, der ist schon geschuetzt — aber
        // ein Modul darf sich darauf nicht verlassen. Wer den Haken spaeter
        // woanders aufruft, haette sonst eine offene Tuer gebaut.
        foreach (glob($wurzel . '/modules/*/hooks/admin_nav.php') as $datei) {
            $inhalt = (string) file_get_contents($datei);
            if (!str_contains($inhalt, 'is_admin()') && !str_contains($inhalt, 'is_owner()')) {
                throw new RuntimeException('Ohne eigene Prüfung: ' . basename(dirname($datei, 2)));
            }
        }
    },

    // ── Wer darf ──────────────────────────────────────────────────────

    'Die Gästegruppe steht nicht zur Wahl' => function () use ($modul) {
        // Ein Haken bei "Gaeste" waere wirkungslos — der Endpunkt weist
        // Nichtangemeldete ab. Wirkungslose Schalter in einer Oberflaeche
        // sind schlimmer als fehlende: sie sehen aus wie eine Zusage.
        $quelle = (string) file_get_contents($modul . '/lib/einstellungen.php');
        if (!str_contains($quelle, "'guests'")) {
            throw new RuntimeException('Die Gästegruppe wird nicht mehr herausgefiltert');
        }
        $admin = (string) file_get_contents($modul . '/ki.php');
        if (str_contains($admin, 'groups_all()')) {
            throw new RuntimeException('Die Adminseite zeigt wieder alle Gruppen, auch die Gäste');
        }
    },

    'Der Endpunkt weist Nichtangemeldete ab' => function () use ($modul) {
        $quelle = (string) file_get_contents($modul . '/api.php');
        if (!preg_match('/if\s*\(!\$cu\)/', $quelle)) {
            throw new RuntimeException('Die Anmeldepflicht im Endpunkt fehlt — '
                . 'dann erzeugt jeder Vorbeikommende Kosten');
        }
    },

    'Der Schlüssel geht verschlüsselt in die Datenbank' => function () use ($modul) {
        $quelle = (string) file_get_contents($modul . '/lib/einstellungen.php');
        if (!str_contains($quelle, 'forum_encrypt')) {
            throw new RuntimeException('Beim Speichern wird nicht verschlüsselt');
        }
        if (!str_contains($quelle, 'forum_decrypt')) {
            throw new RuntimeException('Beim Lesen wird nicht entschlüsselt');
        }
    },

    'Der Schlüssel steht in keiner Ausgabe' => function () use ($modul) {
        // Weder in der Vorlage noch in dem, was der Browser bekommt.
        foreach (['views/ki.php', 'hooks/editor_toolbar.php'] as $datei) {
            $quelle = (string) file_get_contents($modul . '/' . $datei);
            if (preg_match('/\$cfg\[.schluessel.\]|cfg\[\x27schluessel\x27\]/', $quelle)
                && !str_contains($quelle, '!==')) {
                throw new RuntimeException("Der Schlüssel wird in $datei ausgegeben");
            }
        }
    },

    // ── Modell je Befehl ──────────────────────────────────────────────

    'Ohne eigenen Eintrag gilt das Modell von oben' => function () {
        $cfg = ['modell' => 'modell-a', 'modelle' => []];
        if (ki_modell_fuer('kuerzen', $cfg) !== 'modell-a') {
            throw new RuntimeException('Die allgemeine Angabe greift nicht');
        }
    },

    'Ein eigener Eintrag schlägt die allgemeine Angabe' => function () {
        $cfg = ['modell' => 'modell-a', 'modelle' => ['kuerzen' => 'modell-b']];
        if (ki_modell_fuer('kuerzen', $cfg) !== 'modell-b')      throw new RuntimeException('Eigenes Modell wirkte nicht');
        if (ki_modell_fuer('rechtschreibung', $cfg) !== 'modell-a') throw new RuntimeException('Fremder Befehl wurde mitgezogen');
    },

    'Leer bleibt leer — dann entscheidet die Vorgabe des Anbieters' => function () {
        if (ki_modell_fuer('kuerzen', []) !== '') {
            throw new RuntimeException('Ohne Angabe darf hier nichts stehen');
        }
        // Und die Anbieter-Tabelle muss dann eine Vorgabe haben, sonst ginge
        // die Anfrage ohne Modellnamen raus.
        foreach (KI_ANBIETER as $code => $a) {
            if (trim((string) ($a['vorgabe'] ?? '')) === '') {
                throw new RuntimeException("Anbieter $code hat keine Vorgabe");
            }
        }
    },

    'Befehle mit Urteilskraft sind als solche vermerkt' => function () {
        // Der Vermerk steuert den Hinweis im Adminbereich. Gemessen wurde:
        // beim Kuerzen und Zusammenfassen entscheidet das Modell mit, was
        // wegfaellt — bei Rechtschreibung und Uebersetzen nicht.
        foreach (['kuerzen' => true, 'zusammenfassen' => true,
                  'rechtschreibung' => false, 'uebersetzen' => false] as $b => $soll) {
            if (ki_braucht_urteil($b) !== $soll) {
                throw new RuntimeException("Vermerk 'urteil' bei $b ist falsch");
            }
        }
    },

    // ── Freigabe der Befehle ──────────────────────────────────────────

    'Ein neu dazugekommener Befehl ist nicht stillschweigend gesperrt' => function () {
        if (!ki_befehl_frei('frisch_dazu', ['gesperrt' => ['kuerzen']])) {
            throw new RuntimeException('Was nicht in der Sperrliste steht, ist frei — sonst sucht '
                . 'der Betreiber den Fehler an der falschen Stelle');
        }
    },

    'Gesperrte Befehle bleiben gesperrt' => function () {
        if (ki_befehl_frei('kuerzen', ['gesperrt' => ['kuerzen']])) {
            throw new RuntimeException('Die Sperrliste wirkte nicht');
        }
    },

    // ── Systemtext ────────────────────────────────────────────────────

    'Übersetzen ohne gültige Zielsprache läuft nicht los' => function () {
        foreach (['', 'kl', '../de'] as $ziel) {
            try {
                ki_system_text('uebersetzen', $ziel);
                throw new RuntimeException('Zielsprache ' . var_export($ziel, true) . ' wurde angenommen');
            } catch (KiFehler $e) { /* richtig */ }
        }
    },

    'Die Zielsprache steht danach im Systemtext' => function () {
        $text = ki_system_text('uebersetzen', 'en');
        if (str_contains($text, '{ZIEL}')) throw new RuntimeException('Platzhalter blieb stehen');
        if (!str_contains($text, 'Englisch')) throw new RuntimeException('Zielsprache fehlt im Text');
    },

    'Ein unbekannter Befehl erzeugt keinen Systemtext' => function () {
        try {
            ki_system_text('loesche_alles');
            throw new RuntimeException('Unbekannter Befehl wurde angenommen');
        } catch (KiFehler $e) { /* richtig */ }
    },

    'Keine seit PHP 8.5 veralteten curl-Aufrufe' => function () use ($wurzel) {
        // curl_close() ist seit PHP 8.0 wirkungslos und seit 8.5 veraltet. Der
        // Aufruf tut nichts ausser eine Warnung ins Protokoll zu schreiben —
        // und ein Protokoll voller Warnungen liest irgendwann niemand mehr.
        $treffer = [];
        $lauf = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wurzel . '/modules'));
        foreach ($lauf as $datei) {
            if ($datei->getExtension() !== 'php') continue;
            $inhalt = (string) file_get_contents($datei->getPathname());
            // Nur echte Aufrufe, nicht die Erwaehnung in einem Kommentar.
            if (preg_match('/^\s*curl_close\s*\(/m', $inhalt)) {
                $treffer[] = str_replace($wurzel . '/', '', $datei->getPathname());
            }
        }
        if ($treffer) {
            throw new RuntimeException('curl_close() steht noch in: ' . implode(', ', $treffer));
        }
    },

    'Rollen werden beim Namen genannt' => function () use ($wurzel) {
        // "Die Verwaltung" ist kein Rollenname in diesem Forum — es gibt
        // Inhaber, Administratoren, Moderatoren und Mitglieder. Wer liest,
        // "nur die Verwaltung darf das", weiss nicht, ob er gemeint ist.
        // (Als "Verwaltung VON etwas" — IP-Verwaltung, Tag-Verwaltung — ist
        // das Wort in Ordnung und wird hier nicht getroffen.)
        $treffer = [];
        foreach (glob($wurzel . '/modules/*/*.php') as $datei) {
            $inhalt = (string) file_get_contents($datei);
            if (preg_match('/(die|der) Verwaltung\b/u', $inhalt)) {
                $treffer[] = str_replace($wurzel . '/', '', $datei);
            }
        }
        if ($treffer) {
            throw new RuntimeException('"die Verwaltung" statt eines Rollennamens in: '
                . implode(', ', $treffer));
        }
    },

    'Sichtbare Texte tragen echte Umlaute' => function () {
        // Kommentare und Systemtexte duerfen ASCII bleiben — das ist Hausstil
        // und niemand liest sie. Was im Forum steht, nicht: "laesst" faellt
        // dem Benutzer auf und sieht nach Nachlaessigkeit aus.
        foreach (ki_befehle() as $code => $b) {
            foreach (['name', 'hinweis'] as $feld) {
                if (preg_match('/\b(ae|oe|ue|ss)\b|laesst|koennen|muessen|fuer|ueber(?!setz)/u', $b[$feld])) {
                    throw new RuntimeException("Ersatzschreibweise im sichtbaren Text von $code: "
                        . var_export($b[$feld], true));
                }
            }
        }
        foreach (KI_SPRACHEN as $s) {
            if (preg_match('/(ae|oe|ue)/', $s) && !preg_match('/(Que|ue-)/', $s)) {
                // Franzoesisch/Tuerkisch schreiben sich mit Umlaut, nicht mit ue.
                throw new RuntimeException('Ersatzschreibweise in der Sprachliste: ' . $s);
            }
        }
    },

    'Die Grundregeln schützen Zitate, Quelltext und den Sinn' => function () {
        foreach ([
            'quote'  => 'Zitate bleiben unangetastet',
            'code'   => 'Quelltext bleibt unangetastet',
            'Angaben hinzu' => 'nichts hinzuerfinden',
            'Verneinungen'  => 'Verneinungen bleiben Verneinungen',
        ] as $muster => $wofuer) {
            if (!str_contains(KI_GRUNDREGELN, $muster)) {
                throw new RuntimeException("Regel fehlt: $wofuer (kein '$muster' im Text)");
            }
        }
        // Und jeder Befehl muss die Grundregeln tatsaechlich mitfuehren.
        // Gesucht wird die Sache selbst, nicht ihre Beschreibung: [quote] steht
        // im Regeltext, das Wort "Zitat" nicht.
        foreach (ki_befehle() as $code => $b) {
            if (!str_contains($b['system'], '[quote]')) {
                throw new RuntimeException("Befehl $code laeuft ohne die Grundregeln");
            }
        }
    },
];
