<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/lang.php – Mehrsprachigkeit

function load_language(string $code = 'de'): array {
    $file = __DIR__ . '/../lang/' . preg_replace('/[^a-z]/', '', $code) . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/../lang/de.php';
    }
    return require $file;
}

function current_language(): string {
    // Priorität: User-Einstellung > Cookie > Default
    $u = current_user();
    if ($u && !empty($u['language'])) return $u['language'];
    if (!empty($_COOKIE['forum_lang'])) {
        $c = preg_replace('/[^a-z]/', '', $_COOKIE['forum_lang']);
        if (in_array($c, ['de', 'en'], true)) return $c;
    }
    return 'de';
}

function t(string $key, ...$args): string {
    static $lang = null;
    if ($lang === null) {
        $lang = load_language(current_language());
    }
    $str = $lang[$key] ?? $key;
    if ($args) return vsprintf($str, $args);
    return $str;
}

function available_languages(): array {
    return ['de' => 'Deutsch', 'en' => 'English'];
}
