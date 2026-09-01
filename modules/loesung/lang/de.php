<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/loesung/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'loesung_denied' => 'Das darf nur der Fragesteller oder die Moderation.',
    'loesung_disabled' => 'Das Modul ist nicht aktiv.',
    'loesung_failed' => 'Das hat nicht geklappt.',
    'loesung_marked' => 'Beitrag als Lösung markiert.',
    'loesung_post_missing' => 'Diesen Beitrag gibt es in diesem Thema nicht.',
    'loesung_unmarked' => 'Markierung aufgehoben.',
    'loesung_solved_post' => 'Dieser Beitrag hat das Problem gelöst',
    'loesung_unmark' => 'Markierung aufheben',
    'loesung_mark' => 'Als Lösung markieren',
    'loesung_thread_solved' => 'Dieses Thema ist gelöst',
    'loesung_by' => 'Die Lösung kam von %s.',
    'loesung_see_below' => 'Der markierte Beitrag steht weiter unten.',
    'loesung_modul_name' => 'Als Lösung markieren',
    'loesung_modul_beschreibung' => 'Der Fragesteller kann eine Antwort als Lösung auszeichnen. Der Beitrag bekommt einen grünen Balken, oben im Thema steht ein Sprung dorthin.',
];
