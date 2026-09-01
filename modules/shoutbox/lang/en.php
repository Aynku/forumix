<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/shoutbox/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'shoutbox_disabled' => 'Module not switched on',
    'shoutbox_empty_input' => 'Please type something',
    'shoutbox_loading' => 'Loading messages…',
    'shoutbox_empty' => 'No messages yet. Be the first. 🚀',
    'shoutbox_modul_name' => 'Shoutbox',
    'shoutbox_modul_beschreibung' => 'A small chat box on the front page with live updates. Signed-in users can post short messages.',
];
