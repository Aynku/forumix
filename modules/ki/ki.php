<?php
// modules/ki/ki.php – Einstellungen der Schreibhilfe
//
// Was hier eingetragen wird, kostet Geld — deshalb steht auf dieser Seite an
// mehreren Stellen, wer zahlt und was die Bremsen tun.

if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/lib/einstellungen.php';
require_once __DIR__ . '/lib/befehle.php';
require_once __DIR__ . '/lib/moderation.php';

if (!module_enabled('ki')) {
    error_page(404, 'Nicht gefunden', 'Die KI-Schreibhilfe ist nicht aktiv.');
}
// Der Punkt im Adminbereich ist fuer andere ausgeblendet — das ersetzt
// keine Pruefung.
if (!(is_admin() || is_owner())) {
    error_page(403, 'Kein Zugriff', 'Diese Einstellungen dürfen nur Inhaber und Administratoren sehen.');
}

$probe_ergebnis = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    }

    $alt = ki_einstellungen();

    // Ein leeres Schluesselfeld heisst "nicht angefasst", nicht "loeschen" —
    // sonst waere der Schluessel weg, sobald jemand nur die Bremse aendert.
    // Zum Entfernen gibt es einen eigenen Knopf.
    $schluessel = trim((string) ($_POST['schluessel'] ?? ''));
    if (!empty($_POST['schluessel_weg'])) {
        $schluessel = '';
    } elseif ($schluessel === '') {
        $schluessel = (string) ($alt['schluessel'] ?? '');
    }

    $anbieter = (string) ($_POST['anbieter'] ?? 'anthropic');
    if (!isset(KI_ANBIETER[$anbieter])) $anbieter = 'anthropic';

    $gesperrt = [];
    foreach (array_keys(ki_befehle()) as $code) {
        if (empty($_POST['frei'][$code])) $gesperrt[] = $code;
    }

    $kennzeichnet = [];
    $modelle      = [];
    foreach (array_keys(ki_befehle()) as $code) {
        $kennzeichnet[$code] = !empty($_POST['marke'][$code]);
        $eigenes = trim((string) ($_POST['modell_je'][$code] ?? ''));
        // Nur was ein Modellname sein kann. Der Wert wandert in eine Anfrage
        // an einen fremden Dienst; Leerzeichen und Anfuehrungszeichen haben
        // dort nichts zu suchen.
        if ($eigenes !== '' && preg_match('/^[A-Za-z0-9._-]{1,80}$/', $eigenes)) {
            $modelle[$code] = $eigenes;
        }
    }

    $gruppen = [];
    foreach ((array) ($_POST['gruppen'] ?? []) as $g) {
        $gruppen[] = (int) $g;
    }

    // Wer meldet: nur ein Konto, das es gibt. Die Kennung kommt aus dem
    // Formular, und sie wandert in reports.reporter_id.
    $melder = (int) ($_POST['mod_melder'] ?? 0);
    if ($melder > 0 && !db_fetch('SELECT user_id FROM users WHERE user_id = ?', [$melder])) {
        $melder = 0;
    }

    $neu = [
        'anbieter'     => $anbieter,
        'mod_an'       => !empty($_POST['mod_an']),
        'mod_bis'      => max(0, min(10000, (int) ($_POST['mod_bis'] ?? 20))),
        'mod_melder'   => $melder,
        'mod_modell'   => preg_match('/^[A-Za-z0-9._-]{0,80}$/', (string) ($_POST['mod_modell'] ?? ''))
                            ? trim((string) $_POST['mod_modell']) : '',
        'schluessel'   => $schluessel,
        'modell'       => trim((string) ($_POST['modell'] ?? '')),
        'modelle'      => $modelle,
        'gruppen'      => array_values(array_unique($gruppen)),
        'gesperrt'     => $gesperrt,
        'kennzeichnet' => $kennzeichnet,
        'max_zeichen'  => max(500, min(40000, (int) ($_POST['max_zeichen'] ?? 8000))),
        'pro_stunde'   => max(0, min(500, (int) ($_POST['pro_stunde'] ?? 30))),
    ];
    ki_einstellungen_speichern($neu);

    if (!empty($_POST['probe'])) {
        $fehler = ki_probe(ki_einstellungen());
        $_SESSION['ki_probe'] = $fehler ?? '';
    }

    flash('Einstellungen gespeichert.', 'success');
    redirect(FORUM_URL . '/modules/ki/ki.php');
}

if (array_key_exists('ki_probe', $_SESSION)) {
    $probe_ergebnis = (string) $_SESSION['ki_probe'];
    unset($_SESSION['ki_probe']);
}

// Lag der API-Schluessel noch im Klartext, weil beim Speichern kein
// FORUM_CRYPT_KEY da war: jetzt nachholen und es sagen.
$nachgezogen = ki_schluessel_nachziehen();

$cfg = ki_einstellungen();

// Fehlt der Schluessel in der config.php, nuetzt der blosse Hinweis darauf
// wenig — dann steht der Betreiber davor und weiss nicht, was er eintragen
// soll. Also die fertige Zeile dazu, wie im E-Mail-Bereich. Einmal je Sitzung
// dieselbe: sonst steht nach jedem Neuladen eine andere da, und wer die alte
// schon eingetragen hat, glaubt, er habe sich vertippt.
$crypt_vorschlag = '';
if (!forum_crypt_available()) {
    if (empty($_SESSION['crypt_key_vorschlag'])) {
        $_SESSION['crypt_key_vorschlag'] = forum_crypt_new_key();
    }
    $crypt_vorschlag = $_SESSION['crypt_key_vorschlag'];
}

// Verbrauch der letzten sieben Tage — die Antwort auf "wofuer zahle ich das".
$verbrauch = [];
$gesamt    = 0;
try {
    $verbrauch = db_fetchAll(
        'SELECT befehl, COUNT(*) AS anzahl, SUM(zeichen) AS zeichen
           FROM ki_nutzung
          WHERE created_at > (NOW() - INTERVAL 7 DAY)
       GROUP BY befehl
       ORDER BY anzahl DESC');
    foreach ($verbrauch as $v) $gesamt += (int) $v['anzahl'];
} catch (Exception $e) {}

render('@ki/ki', [
    'title'        => 'KI-Schreibhilfe',
    'cfg'          => $cfg,
    'anbieter'     => KI_ANBIETER,
    'befehle'      => ki_befehle(),
    // Ohne die Gaeste-Gruppe: der Endpunkt weist Nichtangemeldete ab, ein
    // Haken dort waere ein Versprechen, das nie eingeloest wird. Wer ihn
    // setzt, sucht den Fehler danach im Modell.
    'gruppen'      => ki_waehlbare_gruppen(),
    'verbrauch'    => $verbrauch,
    'gesamt'       => $gesamt,
    'probe'        => $probe_ergebnis,
    'crypt_da'     => forum_crypt_available(),
    'crypt_vorschlag' => $crypt_vorschlag,
    'nachgezogen'  => $nachgezogen,
    // Kandidaten fuer das meldende Konto. Nur Verwaltung und Konten ohne
    // Beitraege: ein Melder soll kein Mitglied sein, das nebenbei schreibt.
    'melder_wahl'  => db_fetchAll(
        'SELECT user_id, username, post_count FROM users
          WHERE is_admin = 1 OR COALESCE(is_owner,0) = 1 OR post_count = 0
       ORDER BY post_count, username LIMIT 50'),
    'mod_offen'    => (int) db_count("SELECT COUNT(*) FROM ki_pruefung WHERE status = 'offen'"),
    'mod_befunde'  => db_fetchAll("SELECT befund, COUNT(*) AS anzahl FROM ki_pruefung
                                    WHERE status = 'fertig' AND created_at > (NOW() - INTERVAL 7 DAY)
                                 GROUP BY befund ORDER BY anzahl DESC"),
    'hat_schluessel' => (string) ($cfg['schluessel'] ?? '') !== '',
]);
