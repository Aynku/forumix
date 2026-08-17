<?php
// modules/banner/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'banner',
    'name'        => 'Ankündigungsband',
    'description' => 'Band über der ganzen Seite, für Wartungshinweise und Ankündigungen. Text und Farbe im Modul selbst, Besucher können es wegklicken.',
    'icon'        => '📣',
    'version'     => '1.0',
    // Nur fuer die Verwaltung sichtbar: fuer alle anderen waere es ein Link
    // auf eine Seite, die sie ohnehin nicht bedienen duerfen.
    'menu'        => ['label' => 'Band', 'url' => 'modules/banner/banner.php', 'admin' => true, 'ort' => 'menue'],
    'author'      => 'Forumix',
];
