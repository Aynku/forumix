<?php
// modules/termine/termine.php – Terminliste, oeffentlich lesbar
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

// Aus heisst aus: sonst bliebe die Seite auch im abgeschalteten Zustand erreichbar.
if (!module_enabled('termine')) {
    error_page(404, 'Nicht gefunden', 'Das Termin-Modul ist nicht aktiv.');
}

$cu       = current_user();
$canAdmin = $cu && ($cu['is_admin'] || !empty($cu['is_owner']));
$selbst   = FORUM_URL . '/modules/termine/termine.php';

/**
 * Prueft eine Eingabe aus dem Formular und liefert Fehler oder Werte.
 * Getrennt vom Speichern, weil Anlegen und Aendern dieselben Regeln haben.
 */
function termin_pruefen(array $post): array {
    $fehler = [];
    $titel  = trim($post['titel'] ?? '');
    $beginn = trim($post['beginn'] ?? '');
    $ende   = trim($post['ende'] ?? '');

    if ($titel === '')                 $fehler[] = 'Ohne Titel geht es nicht.';
    if ($beginn === '')                $fehler[] = 'Ohne Beginn geht es nicht.';

    // Das Feld liefert "2026-08-20T19:30". Alles andere kommt nicht aus dem
    // Formular, sondern von jemandem, der daran vorbei sendet.
    $zuDatum = static function (string $s): ?string {
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $s) ?: DateTime::createFromFormat('Y-m-d H:i:s', $s);
        return $d ? $d->format('Y-m-d H:i:s') : null;
    };

    $beginnDb = $beginn !== '' ? $zuDatum($beginn) : null;
    $endeDb   = $ende   !== '' ? $zuDatum($ende)   : null;

    if ($beginn !== '' && $beginnDb === null) $fehler[] = 'Der Beginn ist kein gültiges Datum.';
    if ($ende   !== '' && $endeDb   === null) $fehler[] = 'Das Ende ist kein gültiges Datum.';
    if ($beginnDb && $endeDb && $endeDb < $beginnDb) {
        $fehler[] = 'Das Ende liegt vor dem Beginn.';
    }

    return [$fehler, [
        'titel'        => mb_substr($titel, 0, 140),
        'beschreibung' => mb_substr(trim($post['beschreibung'] ?? ''), 0, 2000),
        'beginn'       => $beginnDb,
        'ende'         => $endeDb,
        'ort'          => mb_substr(trim($post['ort'] ?? ''), 0, 140),
    ]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Token gegen fremde Formulare, Rechte gegen neugierige Besucher — beides.
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    }
    if (!$canAdmin) {
        error_page(403, 'Kein Zugriff', 'Termine dürfen nur Inhaber und Administratoren pflegen.');
    }

    $tun = $_POST['tun'] ?? '';

    if ($tun === 'anlegen' || $tun === 'aendern') {
        [$fehler, $w] = termin_pruefen($_POST);

        if ($fehler) {
            flash(implode(' ', $fehler), 'error');
        } elseif ($tun === 'anlegen') {
            db_execute(
                'INSERT INTO termine (titel, beschreibung, beginn, ende, ort, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$w['titel'], $w['beschreibung'], $w['beginn'], $w['ende'], $w['ort'], (int) $cu['user_id']]
            );
            flash('Termin angelegt.', 'success');
        } else {
            db_execute(
                'UPDATE termine SET titel = ?, beschreibung = ?, beginn = ?, ende = ?, ort = ?
                 WHERE termin_id = ?',
                [$w['titel'], $w['beschreibung'], $w['beginn'], $w['ende'], $w['ort'],
                 (int) ($_POST['id'] ?? 0)]
            );
            flash('Termin geändert.', 'success');
        }
    }

    if ($tun === 'loeschen') {
        db_execute('DELETE FROM termine WHERE termin_id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('Termin gelöscht.', 'success');
    }

    // Nach dem Schreiben umleiten, sonst legt ein Neuladen alles noch einmal an.
    redirect($selbst);
}

// Zum Aendern: den gewaehlten Termin ins Formular vorladen.
$bearbeiten = null;
$editId     = (int) ($_GET['bearbeiten'] ?? 0);
if ($canAdmin && $editId > 0) {
    $bearbeiten = db_fetch('SELECT * FROM termine WHERE termin_id = ?', [$editId]);
}

render('@termine/termine', [
    'title'      => 'Termine',
    'kommende'   => db_fetchAll(
        'SELECT * FROM termine WHERE COALESCE(ende, beginn) >= NOW() ORDER BY beginn ASC'),
    'vergangene' => db_fetchAll(
        'SELECT * FROM termine WHERE COALESCE(ende, beginn) < NOW() ORDER BY beginn DESC LIMIT 20'),
    'canAdmin'   => $canAdmin,
    'bearbeiten' => $bearbeiten,
    'selbst'     => $selbst,
]);
