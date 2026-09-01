<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/online.php – Wer ist online?

const ONLINE_TIMEOUT = 300; // 5 Minuten

/**
 * Aktualisiert/erstellt Online-Eintrag für die aktuelle Session.
 * Wird automatisch bei jedem Seitenaufruf aufgerufen.
 */
function track_online(): void {
    try {
        // Auf der Kommandozeile ist niemand da. bootstrap.php startet trotzdem
        // eine Sitzung, und ohne diese Zeile stand nach jedem Lauf eines
        // Werkzeugs ein Gast mit der Adresse 0.0.0.0 unter "Wer ist online" —
        // das Forum behauptete Besuch, den es nicht gab.
        if (PHP_SAPI === 'cli') return;

        if (session_status() !== PHP_SESSION_ACTIVE) return;

        $sid = session_id();
        if (!$sid) return;

        $user    = current_user();
        $userId  = $user ? (int) $user['user_id'] : null;
        $ip      = client_ip();
        $loc     = location_label();
        $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        try {
            db_execute(
                'INSERT INTO user_online (session_id, user_id, ip, last_seen, location, user_agent)
                 VALUES (?, ?, ?, NOW(), ?, ?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
                                         ip = VALUES(ip),
                                         last_seen = NOW(),
                                         location = VALUES(location),
                                         user_agent = VALUES(user_agent)',
                [$sid, $userId, $ip, $loc, $ua]
            );
        } catch (PDOException $e) {
            // Fallback ohne user_agent-Spalte (alte DB)
            db_execute(
                'INSERT INTO user_online (session_id, user_id, ip, last_seen, location)
                 VALUES (?, ?, ?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
                                         ip = VALUES(ip),
                                         last_seen = NOW(),
                                         location = VALUES(location)',
                [$sid, $userId, $ip, $loc]
            );
        }

        // Wenn eingeloggt: Andere Sessions desselben Users entfernen (vermeidet Dopplung)
        if ($userId) {
            db_execute(
                'DELETE FROM user_online WHERE user_id = ? AND session_id != ?',
                [$userId, $sid]
            );

            // v0.18.52: users.last_activity_at aktualisieren — das ist die "echte"
            // Aktivitäts-Spur (last_login wird nur beim echten Login gesetzt und
            // bleibt bei Remember-Me-Sessions tagelang gleich).
            // Schluckt Fehler still wenn die Spalte (noch) nicht existiert.
            try {
                db_execute(
                    'UPDATE users SET last_activity_at = NOW() WHERE user_id = ?',
                    [$userId]
                );
            } catch (Exception $e) {}
        }

        // 5% Wahrscheinlichkeit: Aufräumen alter Einträge
        if (mt_rand(1, 20) === 1) {
            db_execute(
                'DELETE FROM user_online WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)',
                [ONLINE_TIMEOUT * 4]
            );
            // Auch alte Bot-Visits aufräumen (älter als 90 Tage)
            try {
                db_execute('DELETE FROM bot_visits WHERE last_seen < DATE_SUB(NOW(), INTERVAL 90 DAY)');
            } catch (Exception $e) {}
        }

        // Bot-Visit loggen falls erkannt
        if (!$userId) {
            log_bot_visit($ua, $ip, $loc);
        }
    } catch (Exception $e) {}
}

/**
 * Loggt einen Bot-Besuch in die bot_visits-Tabelle.
 * Wird aus track_online() heraus aufgerufen.
 * - Erkennt Bot via detect_bot() (User-Agent-Match)
 * - Erkennt zusätzlich Stealth-Bots via Heuristik (parallele Sessions von einer IP)
 * - Upsert pro (bot_name, ip): visit_count += 1, last_seen aktualisiert
 */
function log_bot_visit(string $ua, string $ip, string $location): void {
    if ($ua === '' || $ip === '') return;

    $bot = detect_bot($ua);

    // Wenn nicht erkannt: Stealth-Bot-Heuristik
    if (!$bot) {
        $bot = detect_stealth_bot($ua, $ip);
        if (!$bot) return;  // Wirklich kein Bot
    }

    $name     = mb_substr($bot['name'], 0, 100);
    $icon     = $bot['icon'];
    $category = $bot['category'];
    $url      = mb_substr($location, 0, 255);
    $uaShort  = mb_substr($ua, 0, 2000);

    try {
        db_execute(
            'INSERT INTO bot_visits (bot_name, bot_icon, category, ip, user_agent, visit_count, first_seen, last_seen, last_url)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW(), ?)
             ON DUPLICATE KEY UPDATE
                visit_count = visit_count + 1,
                last_seen   = NOW(),
                last_url    = VALUES(last_url),
                user_agent  = VALUES(user_agent),
                bot_icon    = VALUES(bot_icon),
                category    = VALUES(category)',
            [$name, $icon, $category, $ip, $uaShort, $url]
        );
    } catch (Exception $e) {
        // Tabelle existiert ggf. noch nicht (Migration v18_38 nicht gelaufen) – ignorieren
    }
}

/**
 * Stealth-Bot-Heuristik (v0.18.38).
 * Erkennt Bots die sich als normaler Browser tarnen (Chrome/Firefox User-Agent
 * aber Verhalten passt nicht). Indizien:
 *   - Mehrere parallele Sessions von der gleichen IP in den letzten 5 Minuten
 *     (>= 3 → verdächtig)
 *   - Niemals eingeloggt (kein user_id in der Online-Tabelle für diese IP)
 *
 * Wird nur aufgerufen wenn detect_bot() (User-Agent-Match) nichts findet.
 * False-Positives sind möglich aber unwahrscheinlich – Firmen/Schulen hinter
 * NAT könnten theoretisch mehrere Sessions von einer IP haben, aber dann
 * wären meist mindestens einige davon eingeloggt.
 */
function detect_stealth_bot(string $ua, string $ip): ?array {
    if ($ip === '') return null;

    try {
        $count = db_count(
            "SELECT COUNT(*) FROM user_online
             WHERE ip = ? AND user_id IS NULL
               AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            [$ip]
        );
    } catch (Exception $e) {
        return null;
    }

    if ($count < 3) return null;  // Nicht verdächtig

    // Identifier-Name aus User-Agent-Anfang ableiten (für unique-Index)
    // z.B. "Chrome (suspect 72.x.x.x)" – stabil pro IP
    $name = 'Stealth (' . mb_substr(preg_replace('/[^A-Za-z0-9 \.\/]/', '', $ua), 0, 30) . ')';

    return [
        'icon'     => '🕵️',
        'name'     => $name,
        'category' => 'suspect',
    ];
}

/**
 * Erkennt bekannte Bots/Crawler am User-Agent-String.
 * Gibt entweder den Bot-Namen zurück (z.B. "Googlebot") oder null wenn kein Bot.
 */
function detect_bot(?string $userAgent): ?array {
    if (!$userAgent) return null;
    $ua = strtolower($userAgent);

    // Bot-Datenbank: [icon, lowercase-pattern, friendly-name, category]
    // category: 'search' | 'ai' | 'seo' | 'social' | 'monitoring' | 'archive' | 'generic'
    $bots = [
        ['🔍', 'googlebot',          'Googlebot',          'search'],
        ['🔍', 'bingbot',            'Bingbot',            'search'],
        ['🦆', 'duckduckbot',        'DuckDuckBot',        'search'],
        ['🦆', 'duckduckgo',         'DuckDuckGo',         'search'],
        ['🔍', 'yandexbot',          'YandexBot',          'search'],
        ['🔍', 'yandex.com/bots',    'YandexBot',          'search'],
        ['🔍', 'baiduspider',        'Baidu',              'search'],
        ['🦣', 'mastodon',           'Mastodon',           'social'],
        ['🤖', 'gptbot',             'GPTBot (OpenAI)',    'ai'],
        ['🤖', 'oai-searchbot',      'OAI-SearchBot',      'ai'],
        ['🤖', 'chatgpt-user',       'ChatGPT',            'ai'],
        ['🤖', 'claudebot',          'ClaudeBot (Anthropic)', 'ai'],
        ['🤖', 'claude-web',         'Claude Web',         'ai'],
        ['🤖', 'anthropic-ai',       'Anthropic AI',       'ai'],
        ['🤖', 'perplexitybot',      'PerplexityBot',      'ai'],
        ['🤖', 'amazonbot',          'AmazonBot',          'ai'],
        ['🤖', 'applebot',           'Applebot',           'search'],
        ['🤖', 'ccbot',              'CommonCrawl',        'ai'],
        ['🔍', 'sogou',              'Sogou',              'search'],
        ['🔍', 'exabot',             'Exalead',            'search'],
        ['🔍', 'mj12bot',            'Majestic',           'seo'],
        ['📊', 'ahrefsbot',          'AhrefsBot',          'seo'],
        ['📊', 'semrushbot',         'SemrushBot',         'seo'],
        ['📊', 'dotbot',             'DotBot (Moz)',       'seo'],
        ['📊', 'rogerbot',           'RogerBot (Moz)',     'seo'],
        ['📦', 'archive.org_bot',    'Internet Archive',   'archive'],
        ['📦', 'ia_archiver',        'Internet Archive',   'archive'],
        ['📱', 'facebookexternalhit', 'Facebook Crawler',  'social'],
        ['📱', 'meta-externalagent', 'Meta Crawler',       'social'],
        ['📱', 'twitterbot',         'Twitterbot',         'social'],
        ['📱', 'linkedinbot',        'LinkedInBot',        'social'],
        ['📱', 'whatsapp',           'WhatsApp',           'social'],
        ['📱', 'telegrambot',        'TelegramBot',        'social'],
        ['📱', 'discordbot',         'DiscordBot',         'social'],
        ['💻', 'slackbot',           'Slackbot',           'social'],
        ['🛡️', 'pingdom',            'Pingdom Monitor',   'monitoring'],
        ['🛡️', 'uptimerobot',        'UptimeRobot',       'monitoring'],
        ['🛡️', 'site24x7',           'Site24x7',          'monitoring'],
        // Generische Patterns – immer ganz am Ende!
        ['🤖', 'bot/',               'Bot',                'generic'],
        ['🤖', 'crawler',            'Crawler',            'generic'],
        ['🕷️', 'spider',             'Spider',            'generic'],
    ];

    foreach ($bots as [$icon, $pattern, $name, $category]) {
        if (str_contains($ua, $pattern)) {
            return ['icon' => $icon, 'name' => $name, 'category' => $category];
        }
    }
    return null;
}

function location_label(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

    // v0.18.51: "/admin/" nur als "Adminbereich" loggen wenn der User
    // tatsächlich Admin-/Mod-Rechte hat. Sonst sind das Crawler oder
    // gescheiterte Zugriffsversuche, die irreführend "im Adminbereich"
    // erschienen.
    if (str_contains($uri, '/admin/')) {
        if (is_admin() || is_mod()) {
            return 'Adminbereich';
        }
        return 'Forum';  // Crawler/anonyme Zugriffs-Versuche neutral labeln
    }
    if (str_contains($uri, '/install/')) return t('page_install');

    $map = [
        'index.php'         => 'home',
        'board.php'         => 'page_board',
        'thread.php'        => 'page_thread',
        'profile.php'       => 'profile',
        'settings.php'      => 'settings',
        'pm.php'            => 'page_pm',
        'notifications.php' => 'notifications',
        'search.php'        => 'search',
        'login.php'         => 'login',
        'register.php'      => 'register',
        'new_thread.php'    => 'new_thread',
        'edit_post.php'     => 'edit_post_title',
        'imprint.php'       => 'imprint',
        'privacy.php'       => 'privacy',
        'terms.php'         => 'terms',
        'bookmarks.php'     => 'bookmarks',
        'achievements.php'  => 'achievements',
        'online.php'        => 'page_online',
        'members.php'       => 'members',
    ];
    return t($map[$script] ?? 'forum');
}

function online_users(): array {
    try {
        return db_fetchAll('
            SELECT o.*, u.username, u.user_id,
                   COALESCE(u.is_owner, 0) AS is_owner,
                   u.is_admin, u.is_mod, u.is_banned
            FROM user_online o
            LEFT JOIN users u ON u.user_id = o.user_id
            WHERE o.last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY (o.user_id IS NULL) ASC, u.username ASC
        ', [ONLINE_TIMEOUT]);
    } catch (Exception $e) { return []; }
}

/**
 * Prüft ob ein bestimmter User aktuell online ist
 */
function is_user_online(int $userId): bool {
    if ($userId <= 0) return false;
    try {
        return db_count(
            'SELECT COUNT(*) FROM user_online WHERE user_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [$userId, ONLINE_TIMEOUT]
        ) > 0;
    } catch (Exception $e) { return false; }
}

/**
 * Rendert einen kleinen Online/Offline-Punkt.
 * Bei gelöschten Usern wird gar nichts angezeigt – die werden nie wieder online sein.
 *
 * Akzeptiert entweder eine User-ID (int) oder einen User-Datensatz (array).
 * Wenn ein Array übergeben wird, sparen wir uns einen zusätzlichen DB-Query.
 */
function online_dot($userOrId): string {
    // Wenn Array: Daten direkt nutzen
    if (is_array($userOrId)) {
        if (!empty($userOrId['is_deleted'])) {
            return '';
        }
        $userId = (int) ($userOrId['user_id'] ?? 0);
        if (!$userId) return '';
    } else {
        $userId = (int) $userOrId;
        // ID übergeben: dann müssen wir is_deleted aus der DB nachladen
        try {
            $u = db_fetch('SELECT is_deleted FROM users WHERE user_id = ?', [$userId]);
            if ($u && !empty($u['is_deleted'])) {
                return '';
            }
        } catch (Exception $e) {
            // Falls is_deleted-Spalte noch nicht existiert: einfach weitermachen
        }
    }

    $isOnline = is_user_online($userId);
    $color    = $isOnline ? '#22c55e' : '#6b6b6b';
    $title    = $isOnline ? t('status_online') : t('status_offline');
    return '<span class="online-dot" style="background:' . $color . '" title="' . $title . '"></span>';
}

function online_stats(): array {
    try {
        $rows = db_fetchAll(
            'SELECT user_id, user_agent FROM user_online WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [ONLINE_TIMEOUT]
        );

        $total = count($rows);
        $members = 0;
        $bots    = 0;

        foreach ($rows as $r) {
            if ($r['user_id']) {
                $members++;
            } elseif (detect_bot($r['user_agent'] ?? null)) {
                $bots++;
            }
        }

        return [
            'total'   => $total,
            'members' => $members,
            'bots'    => $bots,
            'guests'  => $total - $members - $bots,
        ];
    } catch (Exception $e) {
        // Fallback ohne user_agent-Spalte
        try {
            $total = db_count(
                'SELECT COUNT(*) FROM user_online WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
                [ONLINE_TIMEOUT]
            );
            $members = db_count(
                'SELECT COUNT(*) FROM user_online WHERE user_id IS NOT NULL AND last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
                [ONLINE_TIMEOUT]
            );
            return ['total' => $total, 'members' => $members, 'bots' => 0, 'guests' => $total - $members];
        } catch (Exception $e2) {
            return ['total' => 0, 'members' => 0, 'bots' => 0, 'guests' => 0];
        }
    }
}

/**
 * Bot-Log: Statistik-Übersicht für Admin (Counter pro Kategorie).
 * @return array<string,int> e.g. ['all' => 142, 'search' => 80, 'ai' => 35, 'suspect' => 3]
 */
function bot_log_stats(): array {
    $stats = ['all' => 0, 'search' => 0, 'ai' => 0, 'seo' => 0, 'social' => 0,
              'monitoring' => 0, 'archive' => 0, 'suspect' => 0, 'generic' => 0];
    try {
        $rows = db_fetchAll(
            "SELECT category, COUNT(*) AS cnt
             FROM bot_visits
             GROUP BY category"
        );
        foreach ($rows as $r) {
            $cat = $r['category'];
            if (isset($stats[$cat])) $stats[$cat] = (int) $r['cnt'];
            $stats['all'] += (int) $r['cnt'];
        }
    } catch (Exception $e) {}
    return $stats;
}

/**
 * Bot-Log: Liste für Admin-Anzeige.
 * @param string $filter 'all' | 'search' | 'ai' | 'suspect' (entspricht Tab)
 * @param int $limit Max. Anzahl Einträge
 */
function bot_log_list(string $filter = 'all', int $limit = 200): array {
    try {
        if ($filter === 'all') {
            return db_fetchAll(
                'SELECT id, bot_name, bot_icon, category, ip, user_agent,
                        visit_count, first_seen, last_seen, last_url
                 FROM bot_visits
                 ORDER BY last_seen DESC
                 LIMIT ?',
                [$limit]
            );
        }
        return db_fetchAll(
            'SELECT id, bot_name, bot_icon, category, ip, user_agent,
                    visit_count, first_seen, last_seen, last_url
             FROM bot_visits
             WHERE category = ?
             ORDER BY last_seen DESC
             LIMIT ?',
            [$filter, $limit]
        );
    } catch (Exception $e) {
        return [];
    }
}
