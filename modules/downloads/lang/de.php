<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/downloads/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'downloads_added' => 'Datei eingestellt.',
    'downloads_bad_type' => 'Dieser Dateityp ist nicht erlaubt.',
    'downloads_deleted' => 'Datei gelöscht.',
    'downloads_denied' => 'Dateien dürfen nur Inhaber und Administratoren einstellen.',
    'downloads_disabled' => 'Das Download-Modul ist nicht aktiv.',
    'downloads_file_missing' => 'Datei nicht gefunden.',
    'downloads_no_category' => 'Ohne Kategorie',
    'downloads_no_file' => 'Es kam keine Datei an.',
    'downloads_save_failed' => 'Der Eintrag konnte nicht gespeichert werden.',
    'downloads_store_failed' => 'Die Datei konnte nicht abgelegt werden. Schreibrechte auf uploads/ prüfen.',
    'downloads_too_big' => 'Die Datei ist größer als %s.',
    'downloads_too_big_server' => 'Die Datei ist größer, als der Server annimmt.',
    'downloads_beschreibung' => 'Beschreibung',
    'downloads_bis' => 'Bis',
    'downloads_datei' => 'Datei',
    'downloads_datei_einstellen' => 'Datei einstellen',
    'downloads_hochladen' => 'Hochladen',
    'downloads_kategorie' => 'Kategorie',
    'downloads_loeschen' => 'Löschen',
    'downloads_noch_keine_dateien_eingestellt' => 'Noch keine Dateien eingestellt',
    'downloads_schau_spaeter_noch_einmal' => 'Schau später noch einmal vorbei.',
    'downloads_titel' => 'Titel',
    'downloads_menu' => 'Downloads',
    'downloads_modul_name' => 'Downloads',
    'downloads_modul_beschreibung' => 'Dateiablage mit eigener Seite. Admins laden Dateien hoch und gruppieren sie in Kategorien, alle anderen laden herunter. Zählt die Abrufe.',

    'downloads_loeschen_frage' => 'Diese Datei löschen?',
];
