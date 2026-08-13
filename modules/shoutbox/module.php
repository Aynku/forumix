<?php
// modules/shoutbox/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'shoutbox',
    'name'        => 'Shoutbox',
    'description' => 'Mini-Chat-Box auf der Startseite mit Live-Updates. Eingeloggte User können kurze Nachrichten posten.',
    'icon'        => '💬',
    'version'     => '1.0',
    'author'      => 'aynku.de',
];
