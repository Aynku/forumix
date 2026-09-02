<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/termine/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'termine_added' => 'Event created.',
    'termine_deleted' => 'Event deleted.',
    'termine_denied' => 'Only owners and administrators may manage events.',
    'termine_disabled' => 'The events module is not switched on.',
    'termine_end_before_start' => 'The end is before the start.',
    'termine_end_invalid' => 'The end is not a valid date.',
    'termine_start_invalid' => 'The start is not a valid date.',
    'termine_start_required' => 'A start time is required.',
    'termine_updated' => 'Event changed.',
    'termine_beginn' => 'Start',
    'termine_beschreibung' => 'Description',
    'termine_ende' => 'End',
    'termine_kommend' => 'Upcoming',
    'termine_loeschen' => 'Delete',
    'termine_noch_keine_termine_eingetragen' => 'No events yet',
    'termine_ort' => 'Place',
    'termine_schau_spaeter_noch_einmal' => 'Have another look later.',
    'termine_titel' => 'Title',
    'termine_vorbei' => 'Past',
    'termine_edit' => 'Change event',
    'termine_new' => 'New event',
    'termine_save_change' => 'Save change',
    'termine_create' => 'Create event',
    'termine_next' => 'Upcoming events',
    'termine_menu' => 'Events',
    'termine_modul_name' => 'Events',
    'termine_modul_beschreibung' => 'A list of events with its own page. The next few also appear at the top of the front page. Admins create and maintain them.',

    'termine_loeschen_frage' => 'Delete this event?',
];
