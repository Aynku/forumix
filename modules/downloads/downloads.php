<?php
// modules/downloads/downloads.php – Dateiablage, oeffentlich lesbar
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

if (!module_enabled('downloads')) {
    error_page(404, 'Nicht gefunden', 'Das Download-Modul ist nicht aktiv.');
}

// Dieselbe Liste wie bei den Beitrags-Anhaengen. Bewusst nicht laenger: was
// der Kern nicht annimmt, soll auch hier nicht liegen.
const DOWNLOAD_MAX_SIZE = 20 * 1024 * 1024; // 20 MB

function download_dir(): string {
    $d = UPLOAD_DIR . 'downloads/';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function download_groesse(int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024,    0, ',', '.') . ' KB';
    return $bytes . ' Bytes';
}

$cu       = current_user();
$canAdmin = $cu && ($cu['is_admin'] || !empty($cu['is_owner']));
$selbst   = FORUM_URL . '/modules/downloads/downloads.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    }
    if (!$canAdmin) {
        error_page(403, 'Kein Zugriff', 'Dateien dürfen nur Inhaber und Administratoren einstellen.');
    }

    $tun = $_POST['tun'] ?? '';

    if ($tun === 'hochladen') {
        $titel     = mb_substr(trim($_POST['titel'] ?? ''), 0, 140);
        $kategorie = mb_substr(trim($_POST['kategorie'] ?? ''), 0, 80);
        $text      = mb_substr(trim($_POST['beschreibung'] ?? ''), 0, 2000);
        $f         = $_FILES['datei'] ?? null;

        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            // Der haeufigste Fall ist nicht "kaputt", sondern "groesser als
            // php.ini erlaubt" — dann kommt UPLOAD_ERR_INI_SIZE.
            $grund = (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE)
                ? 'Die Datei ist größer, als der Server annimmt.'
                : 'Es kam keine Datei an.';
            flash($grund, 'error');
        } elseif ($titel === '') {
            flash('Ohne Titel geht es nicht.', 'error');
        } elseif ($f['size'] > DOWNLOAD_MAX_SIZE) {
            flash('Die Datei ist größer als ' . download_groesse(DOWNLOAD_MAX_SIZE) . '.', 'error');
        } else {
            // Der Typ wird am Inhalt bestimmt, nicht am Namen und nicht an dem,
            // was der Browser behauptet.
            $mime = (string) mime_content_type($f['tmp_name']);

            if (!isset(ATTACHMENT_ALLOWED_MIMES[$mime])) {
                flash('Dieser Dateityp ist nicht erlaubt.', 'error');
            } else {
                $ext  = ATTACHMENT_ALLOWED_MIMES[$mime];
                $name = 'dl_' . bin2hex(random_bytes(8)) . '.' . $ext;

                if (!move_uploaded_file($f['tmp_name'], download_dir() . $name)) {
                    flash('Die Datei konnte nicht abgelegt werden. Schreibrechte auf uploads/ prüfen.', 'error');
                } else {
                    try {
                        db_execute(
                            'INSERT INTO downloads
                                (titel, beschreibung, kategorie, dateiname, originalname, mime, groesse, created_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                            [$titel, $text, $kategorie, $name,
                             mb_substr((string) $f['name'], 0, 255), $mime,
                             (int) $f['size'], (int) $cu['user_id']]
                        );
                        flash('Datei eingestellt.', 'success');
                    } catch (Exception $e) {
                        // Sonst liegt eine Datei ohne Eintrag herum, die niemand
                        // mehr sieht und niemand mehr loeschen kann.
                        @unlink(download_dir() . $name);
                        error_log('downloads: ' . $e->getMessage());
                        flash('Der Eintrag konnte nicht gespeichert werden.', 'error');
                    }
                }
            }
        }
    }

    if ($tun === 'loeschen') {
        $row = db_fetch('SELECT dateiname FROM downloads WHERE download_id = ?',
                        [(int) ($_POST['id'] ?? 0)]);
        if ($row) {
            // basename(), obwohl der Wert aus der eigenen Datenbank kommt —
            // ein unlink baut man nicht aus ungeprueften Bestandteilen.
            @unlink(download_dir() . basename($row['dateiname']));
            db_execute('DELETE FROM downloads WHERE download_id = ?', [(int) $_POST['id']]);
            flash('Datei gelöscht.', 'success');
        }
    }

    redirect($selbst);
}

$alle = db_fetchAll('SELECT * FROM downloads ORDER BY kategorie ASC, created_at DESC');

// Nach Kategorie gruppieren; alles ohne Kategorie kommt unter "Ohne Kategorie".
$nachKategorie = [];
foreach ($alle as $d) {
    $nachKategorie[$d['kategorie'] !== '' ? $d['kategorie'] : 'Ohne Kategorie'][] = $d;
}

render('@downloads/downloads', [
    'title'         => 'Downloads',
    'nachKategorie' => $nachKategorie,
    'kategorien'    => array_values(array_unique(array_filter(array_column($alle, 'kategorie')))),
    'canAdmin'      => $canAdmin,
    'maxGroesse'    => download_groesse(DOWNLOAD_MAX_SIZE),
    'selbst'        => $selbst,
]);
