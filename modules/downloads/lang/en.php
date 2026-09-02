<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/downloads/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'downloads_added' => 'File uploaded.',
    'downloads_bad_type' => 'That file type is not allowed.',
    'downloads_deleted' => 'File deleted.',
    'downloads_denied' => 'Only owners and administrators may upload files.',
    'downloads_disabled' => 'The downloads module is not switched on.',
    'downloads_file_missing' => 'File not found.',
    'downloads_no_category' => 'No category',
    'downloads_no_file' => 'No file arrived.',
    'downloads_save_failed' => 'The entry could not be saved.',
    'downloads_store_failed' => 'The file could not be stored. Check write permissions on uploads/.',
    'downloads_too_big' => 'The file is larger than %s.',
    'downloads_too_big_server' => 'The file is larger than the server accepts.',
    'downloads_beschreibung' => 'Description',
    'downloads_bis' => 'Up to',
    'downloads_datei' => 'File',
    'downloads_datei_einstellen' => 'Upload a file',
    'downloads_hochladen' => 'Upload',
    'downloads_kategorie' => 'Category',
    'downloads_loeschen' => 'Delete',
    'downloads_noch_keine_dateien_eingestellt' => 'No files uploaded yet',
    'downloads_schau_spaeter_noch_einmal' => 'Have another look later.',
    'downloads_titel' => 'Title',
    'downloads_menu' => 'Downloads',
    'downloads_modul_name' => 'Downloads',
    'downloads_modul_beschreibung' => 'A file store with its own page. Admins upload files and group them into categories, everyone else downloads. Counts the downloads.',

    'downloads_loeschen_frage' => 'Delete this file?',
];
