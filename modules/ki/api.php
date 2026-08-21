<?php
// modules/ki/api.php – Endpunkt der Schreibhilfe
//
// Wird vom Editor per fetch() aufgerufen. Nimmt Text und Befehl entgegen,
// gibt den Vorschlag zurueck. Schreibt nichts in einen Beitrag: was mit dem
// Vorschlag geschieht, entscheidet allein der Verfasser im Browser.

if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/lib/einstellungen.php';
require_once __DIR__ . '/lib/befehle.php';
require_once __DIR__ . '/lib/schutz.php';

header('Content-Type: application/json; charset=utf-8');

/** Antwortet und beendet. */
function ki_json(array $daten, int $code = 200): never {
    http_response_code($code);
    die(json_encode($daten, JSON_UNESCAPED_UNICODE));
}

if (!module_enabled('ki')) {
    ki_json(['ok' => false, 'fehler' => 'Die Schreibhilfe ist nicht aktiv.'], 404);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ki_json(['ok' => false, 'fehler' => 'Nur POST.'], 405);
}
if (!verify_csrf($_POST['csrf'] ?? '')) {
    ki_json(['ok' => false, 'fehler' => 'Sicherheits-Token ungültig. Bitte die Seite neu laden.'], 400);
}

$cu = current_user();
if (!$cu) {
    ki_json(['ok' => false, 'fehler' => 'Nicht angemeldet.'], 401);
}
if (!ki_darf($cu)) {
    ki_json(['ok' => false, 'fehler' => 'Deine Gruppe ist für die Schreibhilfe nicht freigegeben.'], 403);
}

$cfg    = ki_einstellungen();
$befehl = (string) ($_POST['befehl'] ?? '');
$ziel   = (string) ($_POST['ziel'] ?? '');
$text   = (string) ($_POST['text'] ?? '');

if (!isset(ki_befehle()[$befehl])) {
    ki_json(['ok' => false, 'fehler' => 'Unbekannter Befehl.'], 400);
}
if (!ki_befehl_frei($befehl, $cfg)) {
    ki_json(['ok' => false, 'fehler' => 'Dieser Befehl ist abgeschaltet.'], 403);
}

$text = trim($text);
if ($text === '') {
    ki_json(['ok' => false, 'fehler' => 'Da ist noch kein Text.'], 400);
}

// Obergrenze fuer die Eingabe. Sie schuetzt zwei Dinge: die Rechnung des
// Betreibers und die Zeitgrenze — ein sehr langer Text laeuft in den Abbruch,
// und der Benutzer sieht nur, dass "nichts passiert".
$grenze = (int) ($cfg['max_zeichen'] ?? 8000);
if ($grenze < 500) $grenze = 8000;
if (mb_strlen($text) > $grenze) {
    ki_json(['ok' => false, 'fehler' => 'Der Text ist zu lang (' . number_format(mb_strlen($text), 0, ',', '.')
        . ' Zeichen, erlaubt sind ' . number_format($grenze, 0, ',', '.') . '). Markiere einen Abschnitt.'], 400);
}

// ── Bremse ────────────────────────────────────────────────────────────
// Ohne sie kann ein einzelner Benutzer an einem Abend eine Rechnung
// erzeugen, die niemand erwartet hat. Die Verwaltung bremst mit, sonst
// faellt es beim Einrichten nicht auf.
$proStunde = (int) ($cfg['pro_stunde'] ?? 30);
if ($proStunde > 0) {
    try {
        $zahl = db_count('SELECT COUNT(*) FROM ki_nutzung WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)',
            [(int) $cu['user_id']]);
        if ($zahl >= $proStunde) {
            ki_json(['ok' => false, 'fehler' => 'Du hast das Stundenlimit erreicht (' . $proStunde
                . '). Bitte später weitermachen.'], 429);
        }
    } catch (Exception $e) {
        // Tabelle fehlt (Modul nie aktiviert): dann greift die Bremse nicht,
        // aber der Dienst soll deshalb nicht stillstehen.
        error_log('[ki] Bremse nicht pruefbar: ' . $e->getMessage());
    }
}

// ── Anfrage ───────────────────────────────────────────────────────────
// Zitate und Quelltext gehen gar nicht erst mit — siehe lib/schutz.php.
// Zuerst die Raender: was vorn oder hinten steht, wird schlicht abgeschnitten
// und nach der Antwort wieder angesetzt. Nur was mittendrin liegt, braucht
// eine Marke, die das Modell durchreichen muss.
[$vorspann, $mitte, $nachspann] = ki_raender_abtrennen($text);
[$gekuerzt, $bloecke]           = ki_bloecke_heraus($mitte);

if (!ki_hat_eigenen_text($gekuerzt)) {
    ki_json(['ok' => false, 'fehler' => 'In dem Beitrag stehen nur Zitate und Quelltext. Daran wird nichts geändert.'], 400);
}

try {
    $system = ki_system_text($befehl, $ziel);
    // Jeder Befehl darf sein eigenes Modell haben — Rechtschreibung braucht
    // kein grosses, Zusammenfassen schon.
    $cfg['modell'] = ki_modell_fuer($befehl, $cfg);

    // Das Modell muss wissen, dass die Marken stehenbleiben sollen — sonst
    // "raeumt" es sie beim Umformulieren weg. ⟦BLOCK…⟧ setzt der Server fuer
    // Zitate und Quelltext, ⟦DATEN…⟧ der Browser fuer geschwaerzte Angaben;
    // die sieht der Server selbst nie.
    if ($bloecke || preg_match('/⟦(BLOCK|DATEN)\d+⟧/u', $gekuerzt)) {
        $system .= "\n\nIm Text stehen Marken der Form ⟦BLOCK0⟧ oder ⟦DATEN0⟧.\n"
                 . "Sie stehen fuer Zitate, Quelltext und Angaben, die du nicht siehst und\n"
                 . "nicht brauchst. Gib jede Marke unveraendert und an derselben Stelle\n"
                 . "zurueck. Schreibe keine neuen Marken dazu.";
    }

    $ergebnis = ki_anfrage($system, $gekuerzt, $cfg);
    [$ergebnis, $verschluckt] = ki_bloecke_zurueck($ergebnis, $bloecke);
    $ergebnis = $vorspann . $ergebnis . $nachspann;
} catch (KiFehler $e) {
    ki_json(['ok' => false, 'fehler' => $e->getMessage()], 502);
} catch (Throwable $e) {
    error_log('[ki] unerwartet: ' . $e->getMessage());
    ki_json(['ok' => false, 'fehler' => 'Da ist etwas schiefgegangen.'], 500);
}

try {
    db_execute('INSERT INTO ki_nutzung (user_id, befehl, zeichen) VALUES (?, ?, ?)',
        [(int) $cu['user_id'], $befehl, mb_strlen($text)]);
} catch (Exception $e) {}

// ── Kennzeichnung vormerken ───────────────────────────────────────────
// Der Browser schickt beim Absenden ein verstecktes Feld mit. Wer es
// entfernt, kaeme ohne Kennzeichnung davon — deshalb merkt sich die Sitzung
// zusaetzlich den Fingerabdruck des gelieferten Textes. Steht der spaeter so
// im Beitrag, wird gekennzeichnet, ganz gleich was das Formular behauptet.
//
// Was das NICHT leistet: wer den Vorschlag noch von Hand umschreibt, faellt
// aus beiden Wegen heraus. Dann hat er allerdings auch selbst Hand angelegt.
$kennzeichnet = ki_kennzeichnet($befehl, $cfg);
if ($kennzeichnet) {
    if (!isset($_SESSION['ki_marken']) || !is_array($_SESSION['ki_marken'])) {
        $_SESSION['ki_marken'] = [];
    }
    $_SESSION['ki_marken'][hash('sha256', $ergebnis)] = $befehl;
    // Nicht unbegrenzt mitschleppen: die letzten zwanzig reichen fuer eine
    // Sitzung, in der jemand an einem Beitrag arbeitet.
    if (count($_SESSION['ki_marken']) > 20) {
        $_SESSION['ki_marken'] = array_slice($_SESSION['ki_marken'], -20, null, true);
    }
}

$antwort = [
    'ok'           => true,
    'text'         => $ergebnis,
    'kennzeichnet' => $kennzeichnet,
    'befehl'       => $befehl,
];

// Wenn das Modell eine Marke verschluckt hat, steht der Block jetzt am Ende
// statt an seiner Stelle. Das muss der Verfasser wissen, bevor er uebernimmt.
if (!empty($verschluckt)) {
    $antwort['hinweis'] = $verschluckt === 1
        ? 'Ein Zitat oder Code-Block wurde ans Ende gesetzt — bitte vor dem Übernehmen prüfen.'
        : $verschluckt . ' Zitate oder Code-Blöcke wurden ans Ende gesetzt — bitte vor dem Übernehmen prüfen.';
}

ki_json($antwort);
