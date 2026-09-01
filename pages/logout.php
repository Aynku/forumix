<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
require_once __DIR__ . '/../bootstrap.php';

// Abmelden ist eine Aenderung, kein Abruf: per GET liess sich jeder Besucher
// von einer fremden Seite aus abmelden (Bild auf /logout.php genuegte), und
// dabei wird die Angemeldet-bleiben-Marke aus der Datenbank geloescht.
//
// Deshalb: nur POST, nur mit Token. Der Menuepunkt ist ein kleines Formular.
// Ein GET-Aufruf zeigt eine Seite mit Knopf, statt einfach abzumelden.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf(post_str('csrf'))) {
    try { remember_destroy_current(); } catch (Exception $e) {}
    logout_user();
    flash(t('logout_done'), 'info');
    redirect(FORUM_URL . '/index.php');
}

if (!is_logged_in()) redirect(FORUM_URL . '/index.php');

render('logout', ['title' => t('logout')]);
