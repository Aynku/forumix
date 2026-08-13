<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// imprint.php – Impressum
require_once __DIR__ . '/../bootstrap.php';

render('imprint', ['title' => 'Impressum']);
