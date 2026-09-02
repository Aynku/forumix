<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/loesung/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'loesung_denied' => 'Only the person who asked, or a moderator, may do that.',
    'loesung_disabled' => 'The module is not switched on.',
    'loesung_failed' => 'That did not work.',
    'loesung_marked' => 'Post marked as the solution.',
    'loesung_post_missing' => 'That post does not exist in this thread.',
    'loesung_unmarked' => 'Mark removed.',
    'loesung_solved_post' => 'This post solved the problem',
    'loesung_unmark' => 'Remove the mark',
    'loesung_mark' => 'Mark as the solution',
    'loesung_thread_solved' => 'This thread is solved',
    'loesung_by' => 'The solution came from %s.',
    'loesung_see_below' => 'The marked post is further down.',
    'loesung_modul_name' => 'Mark as the solution',
    'loesung_modul_beschreibung' => 'The person who asked can mark an answer as the solution. That post gets a green bar, and a link to it appears at the top of the thread.',

    // ── Nachgezogen ──
    'loesung_springen' => 'Jump to the solution',
];
