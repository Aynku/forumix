<?php
// modules/loesung/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

// Kein 'menu': das Modul hat keine eigene Seite, es haengt sich in die
// Themenansicht ein.
return [
    'code'        => 'loesung',
    'name'        => 'loesung_modul_name',
    'description' => 'loesung_modul_beschreibung',
    'icon'        => '✅',
    'version'     => '1.0',
    'author'      => 'Forumix',
];
