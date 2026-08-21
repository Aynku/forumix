<?php
/**
 * install/import-phpbb.php – ein phpBB-Forum nach Forumix holen.
 *
 *   php install/import-phpbb.php --db=phpbb --user=… --pass=… --probe
 *   php install/import-phpbb.php --db=phpbb --user=… --pass=… --dateien=/pfad/zu/phpBB3/files
 *   php install/import-phpbb.php --zuruecknehmen
 *
 * `--leeren` raeumt vorher weg, was hier schon steht — Foren, Themen,
 * Beitraege, Nachrichten und alle Konten ausser dem Inhaber. Wer ein Forum
 * uebernimmt, will meist das uebernommene und nicht die Reste daneben. Das
 * Leeren laesst sich NICHT zurueckdrehen; der Import selbst schon.
 *
 * Denselben Weg gibt es im Adminbereich unter „Import aus phpBB" — wer keinen
 * Zugang zur Kommandozeile hat, braucht ihn dort. Beide Wege benutzen
 * `lib/import_phpbb.php`; hier stehen nur Aufruf und Ausgabe.
 *
 * `--probe` liest und zaehlt, schreibt aber nichts. Der erste Aufruf gehoert
 * immer dorthin: er sagt, was ankommen wuerde — vor allem, wie viele
 * Mitglieder sich hinterher mit ihrem gewohnten Passwort anmelden koennen.
 *
 * `--zuruecknehmen` entfernt wieder, was ein Import angelegt hat, und nur das.
 *
 * Unterstuetzt sind die phpBB-Fassungen 3.2 und 3.3. Bot-Konten bleiben
 * draussen, Rechte und Gruppen ebenso: phpBBs System maschinell abzubilden
 * hiesse, Rechte zu erfinden, die niemand vergeben hat.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Dieser Import läuft nur auf der Kommandozeile. Im Browser: Adminbereich → Import aus phpBB.');
}

$wurzel = dirname(__DIR__);
if (!is_file($wurzel . '/config.php')) {
    fwrite(STDERR, "config.php fehlt — ist Forumix installiert?\n");
    exit(1);
}

// Wie install/migrate.php: eigene Sitzung im temporaeren Verzeichnis, damit
// ein Aufruf als root keine Sitzungsdatei hinterlaesst, die niemandem gehoert.
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');
@session_start();

define('FORUM_RUNNING', true);
require_once $wurzel . '/bootstrap.php';
require_once $wurzel . '/lib/import_phpbb.php';

/* ── Aufruf lesen ─────────────────────────────────────────────────────── */

$opt = ['host' => 'localhost', 'port' => '3306', 'db' => '', 'user' => '', 'pass' => '',
        'praefix' => 'phpbb_', 'dateien' => ''];
$probe = $zurueck = $leeren = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--probe')          { $probe = true;   continue; }
    if ($arg === '--zuruecknehmen')  { $zurueck = true; continue; }
    if ($arg === '--leeren')         { $leeren = true;  continue; }
    if (preg_match('/^--([a-z]+)=(.*)$/s', $arg, $t) && array_key_exists($t[1], $opt)) {
        $opt[$t[1]] = $t[2];
        continue;
    }
    fwrite(STDERR, "Unbekannter Schalter: $arg\nAufruf siehe Kopf dieser Datei.\n");
    exit(2);
}

// Der laufende Zaehler unten arbeitet mit Wagenruecklauf. In einer Logdatei
// stuende sonst jeder Zwischenstand hintereinander in einer einzigen Zeile.
$terminal = function_exists('stream_isatty') && @stream_isatty(STDOUT);

/* ── Zuruecknehmen ────────────────────────────────────────────────────── */

if ($zurueck) {
    if (!import_zuordnung_da()) {
        fwrite(STDERR, "Es gibt nichts zurückzunehmen.\n");
        exit(1);
    }
    echo "── Zurücknehmen ──\n";
    foreach (import_zuruecknehmen() as $was => $n) {
        printf("  %-20s %s entfernt\n", $was, number_format($n, 0, ',', '.'));
    }
    echo "  Zuordnung            entfernt\n";
    exit(0);
}

foreach (['db', 'user'] as $pflicht) {
    if ($opt[$pflicht] === '') {
        fwrite(STDERR, "--$pflicht fehlt. Aufruf siehe Kopf dieser Datei.\n");
        exit(2);
    }
}

/* ── Verbindung und Bestand ───────────────────────────────────────────── */

try {
    $alt = import_verbindung($opt);
} catch (Throwable $e) {
    fwrite(STDERR, 'Keine Verbindung zur phpBB-Datenbank: ' . $e->getMessage() . "\n");
    exit(1);
}

$p = $opt['praefix'];
if (($grund = import_pruefe_quelle($alt, $p)) !== null) {
    fwrite(STDERR, $grund . "\n");
    exit(1);
}

$bs  = import_bestand($alt, $p);
$pw  = import_passwoerter($alt, $p);
$dat = import_dateien_pruefen($alt, $p, $opt['dateien']);
$zahl = fn(int $n): string => number_format($n, 0, ',', '.');

echo "── Gefunden ──\n";
printf("  phpBB-Fassung        %s\n", $bs['fassung'] !== '' ? $bs['fassung'] : 'unbekannt');

if ($bs['fassung'] !== '' && version_compare($bs['fassung'], '3.2.0', '<')) {
    echo "\n  ACHTUNG  Diese Fassung ist älter als 3.2. Beiträge werden dort anders\n";
    echo "           abgelegt; die Formatierung kann darunter leiden.\n\n";
}

foreach ([['Konten', 'konten'], ['Kategorien', 'kategorien'], ['Foren', 'foren'],
          ['Themen', 'themen'], ['Beiträge', 'beitraege'], ['Anhänge', 'anhaenge'],
          ['Umfragen', 'umfragen'], ['Private Nachrichten', 'pn']] as [$label, $k]) {
    printf("  %-20s %s\n", $label, $zahl((int) $bs[$k]));
}
if ($bs['bots'] > 0) {
    printf("  %-20s %s (bleiben draußen)\n", 'Bot-Konten', $zahl((int) $bs['bots']));
}

echo "\n── Passwörter ──\n";
printf("  Werden übernommen    %s (argon2: %d, bcrypt: %d)\n", $zahl($pw['uebernehmbar']),
       $pw['formate']['argon2'] ?? 0, $pw['formate']['bcrypt'] ?? 0);
if ($pw['neu_noetig'] > 0) {
    $rest = array_diff_key($pw['formate'], ['argon2' => 1, 'bcrypt' => 1]);
    $teile = [];
    foreach ($rest as $k => $n) $teile[] = "$k: $n";
    printf("  Müssen neu gesetzt   %s%s\n", $zahl($pw['neu_noetig']),
           $teile ? ' (' . implode(', ', $teile) . ')' : '');
    echo "                       Diese Konten kommen mit, ihre Inhaber benutzen\n";
    echo "                       einmal \"Passwort vergessen\".\n";
}

if ($bs['anhaenge'] > 0) {
    echo "\n── Anhänge ──\n";
    if (!$dat['ordner']) {
        echo "  Kein Ordner angegeben (--dateien) — die Anhänge blieben leer.\n";
        echo "  Gesucht wird, was phpBB unter \"upload_path\" führt, meist phpBB3/files.\n";
    } else {
        printf("  Vorhanden            %s von %s\n", $zahl($dat['gefunden']), $zahl((int) $bs['anhaenge']));
        if ($dat['fehlen'] > 0) {
            printf("  FEHLEN               %s — der Ordner passt nicht zur Datenbank\n",
                   $zahl($dat['fehlen']));
        }
    }
}

echo "\n── Ziel ──\n";
printf("  Forumix              %s\n", forum_version());
printf("  Vorhandene Konten    %s\n", $zahl((int) db_count('SELECT COUNT(*) FROM users')));
printf("  Vorhandene Beiträge  %s\n", $zahl((int) db_count('SELECT COUNT(*) FROM posts')));

if ($probe) {
    echo "\n── Probelauf — es wurde nichts geschrieben ──\n";
    exit(0);
}

/* ── Der Lauf ─────────────────────────────────────────────────────────── */
//
// In Runden, wie im Browser auch: so bleibt der Import fortsetzbar, wenn ihn
// etwas unterbricht.

echo "\n── Import ──\n";

if ($leeren) {
    // Das Konto mit der Kennung 1 ist der Inhaber. Es bleibt stehen: wer
    // sich selbst mitloescht, kommt hinterher nicht mehr hinein.
    $hier = import_ziel_bestand();
    $summe = $hier['foren'] + $hier['themen'] + $hier['beitraege']
           + max(0, $hier['konten'] - 1);
    if ($summe > 0) {
        printf("  Vorher leeren        %s Foren, %s Themen, %s Beiträge, %s Konten\n",
               $zahl($hier['foren']), $zahl($hier['themen']),
               $zahl($hier['beitraege']), $zahl(max(0, $hier['konten'] - 1)));
        foreach (import_leeren(1) as $was => $n) {
            printf("    %-18s %s entfernt\n", $was, $zahl($n));
        }
    } else {
        echo "  Vorher leeren        nichts zu tun\n";
    }
}

import_zuordnung_bereit();

foreach (IMPORT_SCHRITTE as $schritt => $label) {
    $neu = 0;
    while (true) {
        try {
            $e = import_runde($alt, $p, $schritt, $opt);
        } catch (Throwable $ex) {
            printf("%s  %-20s abgebrochen: %s\n", $terminal ? "\r" : '', $label, $ex->getMessage());
            exit(1);
        }
        $neu += (int) ($e['neu'] ?? 0);
        if ($terminal && $neu > 0) printf("\r  %-20s %s …", $label, $zahl($neu));
        if (!empty($e['fertig'])) break;
    }
    printf("%s  %-20s %s\n", $terminal ? "\r" : '', $label,
           $neu > 0 ? $zahl($neu) . ' übernommen' : 'nichts zu tun    ');
}

echo "\n── Fertig ──\n";
echo "  Was zu tun bleibt:\n";
echo "  · Die Rechte je Forum stehen offen — im Adminbereich einrichten.\n";
if ($pw['neu_noetig'] > 0) {
    printf($pw['neu_noetig'] === 1
        ? "  · Ein Mitglied muss einmal \"Passwort vergessen\" benutzen.\n"
        : "  · %s Mitglieder müssen einmal \"Passwort vergessen\" benutzen.\n",
        $zahl($pw['neu_noetig']));
}
echo "  · Ein zweiter Lauf ergänzt nur, was fehlt. --zuruecknehmen macht alles rückgängig.\n";
exit(0);
