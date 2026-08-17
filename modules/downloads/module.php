<?php
// modules/downloads/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'downloads',
    'name'        => 'Downloads',
    'description' => 'Dateiablage mit eigener Seite. Admins laden Dateien hoch und gruppieren sie in Kategorien, alle anderen laden herunter. Zählt die Abrufe.',
    'icon'        => '📥',
    'version'     => '1.0',
    'menu'        => ['label' => 'Downloads', 'url' => 'modules/downloads/downloads.php'],
    'author'      => 'Forumix',
];
