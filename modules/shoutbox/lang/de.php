<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/shoutbox/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'shoutbox_disabled' => 'Modul nicht aktiv',
    'shoutbox_empty_input' => 'Bitte etwas eingeben',
    'shoutbox_loading' => 'Lade Nachrichten…',
    'shoutbox_empty' => 'Noch keine Nachrichten. Sei der/die Erste! 🚀',
    'shoutbox_modul_name' => 'Shoutbox',
    'shoutbox_modul_beschreibung' => 'Mini-Chat-Box auf der Startseite mit Live-Updates. Eingeloggte User können kurze Nachrichten posten.',

    'shoutbox_server_fehler' => 'Server-Fehler. Browser-Konsole für Details öffnen (F12).',
    'shoutbox_senden_fehlgeschlagen' => 'Senden fehlgeschlagen.',
    'shoutbox_netzwerk_fehler' => 'Netzwerk-Fehler. Browser-Konsole für Details (F12).',
    'shoutbox_loeschen_frage' => 'Diese Nachricht löschen?',

    'shoutbox_platzhalter' => 'Schreib was Schönes… (Enter zum Absenden, max. 500 Zeichen)',

    // ── Nachgezogen ──
    'shoutbox_einloggen' => 'Einloggen',
    'shoutbox_zum_nutzen' => ', um die Shoutbox zu nutzen',

    // ── Nachgezogen ──
    'shoutbox_ein_ausklappen' => 'Ein-/Ausklappen',
    'shoutbox_posten' => 'Posten',
];
