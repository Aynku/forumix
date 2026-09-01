<?php
// modules/termine/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'termine',
    'name'        => 'termine_modul_name',
    'description' => 'termine_modul_beschreibung',
    'icon'        => '📅',
    'version'     => '1.0',
    'menu'        => ['label' => 'termine_menu', 'url' => 'modules/termine/termine.php'],
    'author'      => 'Forumix',
];
