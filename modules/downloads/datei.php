<?php
// modules/downloads/datei.php – liefert eine abgelegte Datei aus
//
// Warum nicht direkt auf uploads/downloads/ verlinken: so laesst sich zaehlen,
// der Name auf der Platte bleibt unsichtbar, und wer spaeter eine Zugangsregel
// einbauen will, hat genau eine Stelle dafuer.
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

// Hier keine Fehlerseite im Layout: der Aufrufer erwartet eine Datei.
function datei_weg(int $code): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $code === 404 ? t('downloads_file_missing') : t('error_denied_flash');
    exit;
}

if (!module_enabled('downloads')) datei_weg(404);

$row = db_fetch('SELECT * FROM downloads WHERE download_id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$row) datei_weg(404);

// basename() gegen alles, was sich als Pfad ausgeben koennte.
$pfad = UPLOAD_DIR . 'downloads/' . basename($row['dateiname']);
if (!is_file($pfad)) {
    error_log('downloads: Eintrag ' . $row['download_id'] . ' zeigt auf eine fehlende Datei');
    datei_weg(404);
}

// Zaehlen, bevor gesendet wird — ein Abbruch mitten im Senden soll den Abruf
// nicht verschlucken.
try {
    db_execute('UPDATE downloads SET klicks = klicks + 1 WHERE download_id = ?',
               [(int) $row['download_id']]);
} catch (Exception $e) {
    error_log('downloads: Zaehler: ' . $e->getMessage());
}

// Nur bekannte Typen ausliefern. Steht in der Datenbank etwas anderes, geht es
// als Bytestrom raus statt als das, was dort behauptet wird.
$mime = in_array($row['mime'], array_keys(ATTACHMENT_ALLOWED_MIMES), true)
    ? $row['mime'] : 'application/octet-stream';

// Zeilenumbrueche und Anfuehrungszeichen wuerden den Kopfzeilen-Satz sprengen.
$name = preg_replace('/[\r\n"]+/', '', $row['originalname'] !== '' ? $row['originalname'] : basename($pfad));
$name = trim($name) !== '' ? $name : 'download';

if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($pfad));
header('X-Content-Type-Options: nosniff');
// Zweimal: der einfache Name fuer alte Browser, die UTF-8-Fassung fuer alle
// anderen. Ohne den zweiten Teil werden Umlaute im Dateinamen zu Fragezeichen.
header('Content-Disposition: attachment; filename="' . $name . '"; '
     . "filename*=UTF-8''" . rawurlencode($name));

readfile($pfad);
exit;
