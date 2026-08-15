<?php
// modules/loesung/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

// Kein 'menu': das Modul hat keine eigene Seite, es haengt sich in die
// Themenansicht ein.
return [
    'code'        => 'loesung',
    'name'        => 'Als Lösung markieren',
    'description' => 'Der Fragesteller kann eine Antwort als Lösung auszeichnen. Der Beitrag bekommt einen grünen Balken, oben im Thema steht ein Sprung dorthin.',
    'icon'        => '✅',
    'version'     => '1.0',
    'author'      => 'Forumix',
];
