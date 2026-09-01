<?php
// modules/faq/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'faq',
    'name'        => 'faq_modul_name',
    'description' => 'faq_modul_beschreibung',
    'icon'        => '❓',
    'version'     => '1.0',
    'menu'        => ['label' => 'faq_menu',     'url' => 'modules/faq/faq.php'],
    'author'      => 'Forumix',
];
