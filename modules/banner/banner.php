<?php
// modules/banner/banner.php – Text und Farbe des Bandes
//
// Ohne eigene Tabelle: drei Werte rechtfertigen kein Schema. Sie liegen als
// JSON im Feld config der Tabelle modules, siehe module_config().
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

if (!module_enabled('banner')) {
    error_page(404, 'Nicht gefunden', 'Das Banner-Modul ist nicht aktiv.');
}

// Der Menuepunkt ist fuer andere ausgeblendet — das ersetzt keine Pruefung.
if (!(is_admin() || is_owner())) {
    error_page(403, 'Kein Zugriff', 'Das Band dürfen nur Inhaber und Administratoren setzen.');
}

const BANNER_ARTEN = ['info' => 'Hinweis', 'warnung' => 'Warnung', 'erfolg' => 'Gute Nachricht'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    }

    $art = (string) ($_POST['art'] ?? 'info');

    set_module_config('banner', [
        'text'  => mb_substr(trim($_POST['text'] ?? ''), 0, 300),
        'art'   => isset(BANNER_ARTEN[$art]) ? $art : 'info',
        'aktiv' => !empty($_POST['aktiv']),
    ]);

    flash('Band gespeichert.', 'success');
    redirect(FORUM_URL . '/modules/banner/banner.php');
}

$cfg = module_config('banner');

render('@banner/banner', [
    'title' => 'Ankündigungsband',
    'text'  => (string) ($cfg['text'] ?? ''),
    'art'   => (string) ($cfg['art'] ?? 'info'),
    'aktiv' => !empty($cfg['aktiv']),
    'arten' => BANNER_ARTEN,
]);
