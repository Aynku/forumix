<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/termine/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'termine_added' => 'Termin angelegt.',
    'termine_deleted' => 'Termin gelöscht.',
    'termine_denied' => 'Termine dürfen nur Inhaber und Administratoren pflegen.',
    'termine_disabled' => 'Das Termin-Modul ist nicht aktiv.',
    'termine_end_before_start' => 'Das Ende liegt vor dem Beginn.',
    'termine_end_invalid' => 'Das Ende ist kein gültiges Datum.',
    'termine_start_invalid' => 'Der Beginn ist kein gültiges Datum.',
    'termine_start_required' => 'Ohne Beginn geht es nicht.',
    'termine_updated' => 'Termin geändert.',
    'termine_beginn' => 'Beginn',
    'termine_beschreibung' => 'Beschreibung',
    'termine_ende' => 'Ende',
    'termine_kommend' => 'Kommend',
    'termine_loeschen' => 'Löschen',
    'termine_noch_keine_termine_eingetragen' => 'Noch keine Termine eingetragen',
    'termine_ort' => 'Ort',
    'termine_schau_spaeter_noch_einmal' => 'Schau später noch einmal vorbei.',
    'termine_titel' => 'Titel',
    'termine_vorbei' => 'Vorbei',
    'termine_edit' => 'Termin ändern',
    'termine_new' => 'Neuer Termin',
    'termine_save_change' => 'Änderung speichern',
    'termine_create' => 'Termin anlegen',
    'termine_next' => 'Nächste Termine',
    'termine_menu' => 'Termine',
    'termine_modul_name' => 'Termine',
    'termine_modul_beschreibung' => 'Terminliste mit eigener Seite. Die nächsten Termine erscheinen zusätzlich oben auf der Startseite. Anlegen und pflegen dürfen Admins.',

    'termine_loeschen_frage' => 'Diesen Termin löschen?',

    // ── Nachgezogen ──
    'termine_aendern' => 'Ändern',
];
