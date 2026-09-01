<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// privacy.php – Datenschutzerklärung
require_once __DIR__ . '/../bootstrap.php';

render('privacy', ['title' => t('privacy_title')]);
