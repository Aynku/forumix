<?php
// modules/faq/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'faq',
    'name'        => 'FAQ / Häufige Fragen',
    'description' => 'Strukturierte Hilfe-Seite mit Kategorien (Allgemein, Beiträge, Konto, Moderation). Admins können Fragen pflegen, alle User können sie lesen. Footer-Link.',
    'icon'        => '❓',
    'version'     => '1.0',
    'menu'        => ['label' => 'FAQ',     'url' => 'modules/faq/faq.php'],
    'author'      => 'Forumix',
];
