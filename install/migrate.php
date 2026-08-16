<?php
/**
 * install/migrate.php – Datenbank-Anpassungen von der Kommandozeile.
 *
 *   php install/migrate.php --status          zeigt, was aussteht (aendert nichts)
 *   php install/migrate.php                   faehrt alle ausstehenden, der Reihe nach
 *   php install/migrate.php --version=v18_56  faehrt genau eine
 *   php install/migrate.php --json            Ausgabe als JSON (fuer den Hosting-Dienst)
 *
 * Rueckgabe: 0 alles gut (auch wenn nichts anstand), 1 etwas ist schiefgegangen.
 *
 * ── Warum diese Datei hier liegt und stehenbleibt ──────────────────────
 *
 * `install/cli.php` raeumt nach der Installation den Installer weg, laesst aber
 * `migrations/` stehen — daraus holt der Adminbereich spaetere Anpassungen.
 * Dieser Laeufer gehoert zur selben Sorte: er wird NACH der Installation
 * gebraucht, naemlich bei jedem Update. Laege er in der Aufraeumliste, haette
 * der Update-Lauf des Hosting-Dienstes kein Werkzeug mehr.
 *
 * Eine Datei, die absichtlich im Webspace stehenbleibt, muss ueber HTTP hart
 * dichtmachen. Sie verlaesst sich deshalb NICHT darauf, dass FORUM_RUNNING
 * fehlt, sondern prueft die Laufzeitumgebung selbst.
 */

// ── Riegel ────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Forbidden');
}

$wurzel = dirname(__DIR__);

// lib/auth.php startet beim Einbinden eine Sitzung — aber nur, wenn noch keine
// laeuft. Also starten wir selbst eine, und zwar im temporaeren Verzeichnis:
// sonst legt der Aufruf als root eine Sitzungsdatei im Sitzungsverzeichnis der
// Instanz ab, die dort niemandem gehoert. Nebenbei bleiben damit die Warnungen
// aus, die auth.php sonst auf der Kommandozeile ausgibt (Cookie-Parameter,
// Cache-Limiter — beides gibt es hier nicht).
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');
@session_start();

if (!is_file($wurzel . '/config.php')) {
    fwrite(STDERR, "config.php fehlt — ist das Forum installiert?\n");
    exit(1);
}

define('FORUM_RUNNING', true);
require_once $wurzel . '/bootstrap.php';
require_once $wurzel . '/lib/migrations.php';

// ── Aufruf lesen ──────────────────────────────────────────────────────
$argumente = array_slice($argv, 1);
$alsJson   = in_array('--json', $argumente, true);
$nurStatus = in_array('--status', $argumente, true);
$eine      = null;
foreach ($argumente as $a) {
    if (str_starts_with($a, '--version=')) {
        $eine = substr($a, strlen('--version='));
    } elseif (!in_array($a, ['--json', '--status'], true)) {
        fwrite(STDERR, "Unbekanntes Argument: $a\n");
        exit(1);
    }
}

ensure_migrations_table();

$verfuegbar = available_migrations();
$offen = [];
foreach ($verfuegbar as $m) {
    if (!migration_was_executed($m['version'])) {
        $offen[] = $m;
    }
}

// Eine bestimmte gewuenscht? Dann nur die, und nur wenn sie aussteht.
if ($eine !== null) {
    $offen = array_values(array_filter($offen, fn($m) => $m['version'] === $eine));
    $bekannt = array_filter($verfuegbar, fn($m) => $m['version'] === $eine);
    if (!$bekannt) {
        ausgeben($alsJson, false, "Migration $eine gibt es in diesem Paket nicht.", []);
        exit(1);
    }
}

// ── Status ────────────────────────────────────────────────────────────
if ($nurStatus) {
    ausgeben($alsJson, true, count($offen) . ' ausstehend', [
        'version'     => forum_version(),
        'verfuegbar'  => array_map(fn($m) => $m['version'], $verfuegbar),
        'offen'       => array_map(fn($m) => $m['version'], $offen),
    ]);
    exit(0);
}

// ── Fahren ────────────────────────────────────────────────────────────
$gelaufen = [];
foreach ($offen as $m) {
    $erg = execute_migration($m['version']);
    $gelaufen[] = [
        'version' => $m['version'],
        'name'    => $m['name'],
        'ok'      => $erg['success'],
        'ausgabe' => trim((string) $erg['output']),
    ];
    if (!$erg['success']) {
        // Nicht weiterfahren: die naechste Anpassung baut in aller Regel auf
        // der vorigen auf. Wer hier durchlaeuft, macht aus einem Fehler zwei.
        ausgeben($alsJson, false,
            'Migration ' . $m['version'] . ' fehlgeschlagen: ' . ($erg['error'] ?? 'unbekannt'),
            ['gelaufen' => $gelaufen, 'version' => forum_version()]);
        exit(1);
    }
}

ausgeben($alsJson, true,
    $gelaufen ? count($gelaufen) . ' Migration(en) gelaufen' : 'Nichts ausstehend',
    ['gelaufen' => $gelaufen, 'version' => forum_version()]);
exit(0);

/**
 * Einheitliche Ausgabe. JSON fuer Maschinen, Zeilen fuer Menschen — dieselben
 * Angaben, damit der Hosting-Dienst nichts anderes sieht als jemand, der es
 * von Hand aufruft.
 */
function ausgeben(bool $alsJson, bool $ok, string $text, array $daten): void {
    if ($alsJson) {
        echo json_encode(['ok' => $ok, 'text' => $text] + $daten,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
        return;
    }
    $ziel = $ok ? STDOUT : STDERR;
    fwrite($ziel, ($ok ? '✓ ' : '✗ ') . $text . "\n");
    foreach ($daten['gelaufen'] ?? [] as $g) {
        fwrite($ziel, sprintf("   %-10s %s%s\n", $g['version'], $g['ok'] ? '' : 'FEHLER — ', $g['name']));
    }
    if (isset($daten['offen']) && $daten['offen']) {
        fwrite($ziel, '   offen: ' . implode(', ', $daten['offen']) . "\n");
    }
    if (isset($daten['version'])) {
        fwrite($ziel, '   Version: ' . $daten['version'] . "\n");
    }
}
