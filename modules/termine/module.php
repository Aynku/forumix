<?php
// modules/termine/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'termine',
    'name'        => 'Termine',
    'description' => 'Terminliste mit eigener Seite. Die nächsten Termine erscheinen zusätzlich oben auf der Startseite. Anlegen und pflegen dürfen Admins.',
    'icon'        => '📅',
    'version'     => '1.0',
    'menu'        => ['label' => '📅 Termine', 'url' => 'modules/termine/termine.php'],
    'author'      => 'Forumix',
];
