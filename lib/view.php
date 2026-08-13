<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/view.php

function render(string $view, array $data = []): void {
    $globals = [
        'currentUser'         => current_user(),
        'flash'               => get_flash(),
        'isAdmin'             => is_admin(),
        'isMod'               => is_mod(),
        'csrfToken'           => csrf_token(),
        'unreadPMs'           => 0,
        'unreadNotifications' => 0,
        'title'               => $data['title']      ?? FORUM_NAME,
        'breadcrumb'          => $data['breadcrumb'] ?? [],
        'totalUsers'          => $data['totalUsers'] ?? null,
        'totalPosts'          => $data['totalPosts'] ?? null,
    ];

    if ($globals['currentUser']) {
        $globals['unreadPMs'] = db_count(
            'SELECT COUNT(*) FROM private_messages
             WHERE receiver_id = ? AND is_read = 0 AND deleted_by_receiver = 0',
            [$globals['currentUser']['user_id']]
        );
        $globals['unreadNotifications'] = unread_notifications_count((int)$globals['currentUser']['user_id']);
    }

    if ($globals['totalUsers'] === null) $globals['totalUsers'] = db_count('SELECT COUNT(*) FROM users');
    if ($globals['totalPosts']  === null) $globals['totalPosts']  = db_count('SELECT COUNT(*) FROM posts');

    $data = array_merge($globals, $data);

    // View-Datei finden: erst neue .aynku-Templates, dann alte PHP-Views
    $newTpl = __DIR__ . '/../views_new/' . $view . TPL_EXTENSION;
    if (file_exists($newTpl)) {
        // Neue Engine: kompiliertes Template einbinden
        $content = tpl_render($view, $data);
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../views/layout.php';
        return;
    }

    // ── Fallback: alte PHP-Views ──
    extract($data, EXTR_SKIP);

    // View-Datei finden: erst im Core, dann in Modulen
    $viewFile = __DIR__ . '/../views/' . $view . '.php';
    if (!file_exists($viewFile)) {
        // Modul-Views: Format "@modulcode/viewname" → modules/<code>/views/<viewname>.php
        if (str_starts_with($view, '@')) {
            $parts = explode('/', substr($view, 1), 2);
            if (count($parts) === 2) {
                $viewFile = __DIR__ . '/../modules/' . $parts[0] . '/views/' . $parts[1] . '.php';
            }
        }
        if (!file_exists($viewFile)) die('View nicht gefunden: ' . e($view));
    }

    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require __DIR__ . '/../views/layout.php';
}
