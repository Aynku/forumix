<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
require_once __DIR__ . '/../bootstrap.php';
try { remember_destroy_current(); } catch (Exception $e) {}
logout_user();
flash('Du wurdest abgemeldet.', 'info');
redirect(FORUM_URL . '/index.php');
