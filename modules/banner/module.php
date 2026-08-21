<?php
// modules/banner/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'banner',
    'name'        => 'Ankündigungsband',
    'description' => 'Band über der ganzen Seite, für Wartungshinweise und Ankündigungen. Text und Farbe im Modul selbst, Besucher können es wegklicken.',
    'icon'        => '📣',
    'version'     => '1.0',
    // Kein 'menu': die Seite gehoert in den Adminbereich, nicht in die
    // Hauptnavigation des Forums — siehe hooks/admin_nav.php.
    'author'      => 'Forumix',
];
