<?php
// modules/ki/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'ki',
    'name'        => 'KI-Schreibhilfe',
    'description' => 'Knöpfe im Editor: Rechtschreibung, Kürzen, Zusammenfassen, Übersetzen. '
                   . 'Jedes Ergebnis erscheint erst in einer Vorschau — übernommen wird nur, '
                   . 'was der Verfasser übernimmt. Der API-Schlüssel kommt vom Betreiber.',
    'icon'        => '✨',
    'version'     => '1.0',
    'menu'        => ['label' => 'KI-Schreibhilfe', 'url' => 'modules/ki/ki.php', 'admin' => true, 'ort' => 'menue'],
    'author'      => 'Forumix',
];
