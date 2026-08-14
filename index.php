<?php
/**
 * index.php – Mini-Router
 *
 * Leitet Anfragen auf die richtige Datei in /pages/ um.
 * Wird der Router direkt aufgerufen (/) → Startseite (pages/home.php)
 * Wird der Router via /thread.php?id=1 aufgerufen → pages/thread.php
 *
 * Funktioniert mit der mitgelieferten .htaccess, die /xyz.php → /index.php?_page=xyz umleitet.
 * Ohne URL-Rewrite läuft die alte Variante mit echtem /xyz.php weiterhin als Fallback.
 */

// Marker für die Schutz-Guards in pages/, lib/, views/
define('FORUM_RUNNING', true);

// Welche Seite wurde angefragt?
$page = $_GET['_page'] ?? '';

// Wenn nichts angefordert wurde: Startseite — aber nur, wenn wirklich die
// Wurzel gemeint war. nginx schickt jede unbekannte Adresse hierher; ohne
// diese Unterscheidung antwortet /gibtsnicht mit der Startseite und dem
// Status 200, statt ehrlich 404 zu sagen.
if ($page === '') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/' || $path === '/index.php') {
        require __DIR__ . '/pages/home.php';
        return;
    }
    require __DIR__ . '/bootstrap.php';
    error_page(404, 'Seite nicht gefunden', 'Die angeforderte Adresse gibt es hier nicht.');
}

// Sicherheit: nur a-z, 0-9, _ und - erlauben (Path-Traversal vermeiden)
$page = preg_replace('/[^a-z0-9_-]/i', '', $page);
$path = __DIR__ . '/pages/' . $page . '.php';

if ($page && is_file($path)) {
    require $path;
    return;
}

// Seite nicht gefunden
require __DIR__ . '/bootstrap.php';
error_page(404, 'Seite nicht gefunden', 'Die angeforderte Seite konnte nicht gefunden werden.');
