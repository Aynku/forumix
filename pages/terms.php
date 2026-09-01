<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// terms.php – Nutzungsbedingungen
require_once __DIR__ . '/../bootstrap.php';

render('terms', ['title' => t('terms')]);
