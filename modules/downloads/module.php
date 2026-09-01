<?php
// modules/downloads/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'downloads',
    'name'        => 'downloads_modul_name',
    'description' => 'downloads_modul_beschreibung',
    'icon'        => '📥',
    'version'     => '1.0',
    'menu'        => ['label' => 'downloads_menu', 'url' => 'modules/downloads/downloads.php'],
    'author'      => 'Forumix',
];
