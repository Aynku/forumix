<?php
// modules/banner/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'banner',
    'name'        => 'banner_modul_name',
    'description' => 'banner_modul_beschreibung',
    'icon'        => '📣',
    'version'     => '1.0',
    // Kein 'menu': die Seite gehoert in den Adminbereich, nicht in die
    // Hauptnavigation des Forums — siehe hooks/admin_nav.php.
    'author'      => 'Forumix',
];
