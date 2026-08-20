<?php
// lib/dashboard.php – Persönliche Statistiken für das User-Dashboard
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Sammelt alle Dashboard-Daten für einen User in einem Aufruf
 */
function dashboard_data(int $userId): array {
    $data = [
        'user'              => null,
        'overview'          => [],
        'streak'            => ['current' => 0, 'longest' => 0, 'today' => false],
        'heatmap'           => [],
        'top_posts'         => [],
        'top_threads'       => [],
        'top_interactions'  => [],
        'achievements'      => ['total' => 0, 'unlocked' => 0, 'recent' => []],
        'recent_activity'   => [],
    ];

    if ($userId <= 0) return $data;

    // Jeder Aufruf eigenständig - ein Fehler darf nicht alles kaputtmachen
    try { $data['user']             = dashboard_user($userId); }              catch (Exception $e) { error_log('[dashboard] user: '             . $e->getMessage()); }
    try { $data['overview']         = dashboard_overview($userId); }          catch (Exception $e) { error_log('[dashboard] overview: '         . $e->getMessage()); }
    try { $data['streak']           = dashboard_streak($userId); }            catch (Exception $e) { error_log('[dashboard] streak: '           . $e->getMessage()); }
    try { $data['heatmap']          = dashboard_heatmap($userId); }           catch (Exception $e) { error_log('[dashboard] heatmap: '          . $e->getMessage()); }
    try { $data['top_posts']        = dashboard_top_posts($userId); }         catch (Exception $e) { error_log('[dashboard] top_posts: '        . $e->getMessage()); }
    try { $data['top_threads']      = dashboard_top_threads($userId); }       catch (Exception $e) { error_log('[dashboard] top_threads: '      . $e->getMessage()); }
    try { $data['top_interactions'] = dashboard_top_interactions($userId); }  catch (Exception $e) { error_log('[dashboard] top_interactions: ' . $e->getMessage()); }
    try { $data['achievements']     = dashboard_achievements($userId); }      catch (Exception $e) { error_log('[dashboard] achievements: '     . $e->getMessage()); }
    try { $data['recent_activity']  = dashboard_recent_activity($userId); }   catch (Exception $e) { error_log('[dashboard] recent_activity: '  . $e->getMessage()); }

    return $data;
}

function dashboard_user(int $userId): ?array {
    // SELECT * holt alle Spalten, egal welche Version – sicherste Variante
    try {
        $row = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
        if ($row) {
            // Sicherstellen dass die wichtigen Felder existieren (mit Defaults)
            $row['is_admin']      = $row['is_admin']      ?? 0;
            $row['is_mod']        = $row['is_mod']        ?? 0;
            $row['is_owner']      = $row['is_owner']      ?? 0;
            $row['rank']          = $row['rank']          ?? 'Neuling';
            $row['post_count']    = $row['post_count']    ?? 0;
            $row['avatar']        = $row['avatar']        ?? null;
            $row['registered_at'] = $row['registered_at'] ?? null;
            return $row;
        }
    } catch (Exception $e) {
        error_log('[dashboard_user] SELECT * fehlgeschlagen: ' . $e->getMessage());
    }

    return null;
}

function dashboard_overview(int $userId): array {
    try {
        // Beiträge gesamt + diese Woche + dieser Monat
        $total   = db_count('SELECT COUNT(*) FROM posts WHERE user_id = ? AND COALESCE(is_approved, 1) = 1 AND deleted_at IS NULL', [$userId]);
        $week    = db_count('SELECT COUNT(*) FROM posts WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND COALESCE(is_approved, 1) = 1 AND deleted_at IS NULL', [$userId]);
        $month   = db_count('SELECT COUNT(*) FROM posts WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND COALESCE(is_approved, 1) = 1 AND deleted_at IS NULL', [$userId]);

        // Threads gestartet
        $threads = db_count('SELECT COUNT(*) FROM threads WHERE user_id = ? AND COALESCE(is_approved, 1) = 1 AND deleted_at IS NULL', [$userId]);

        // Reaktionen erhalten
        $reactionsReceived = 0;
        try {
            $reactionsReceived = db_count(
                'SELECT COUNT(*) FROM reactions r JOIN posts p ON p.post_id = r.post_id WHERE p.user_id = ?',
                [$userId]
            );
        } catch (Exception $e) {}

        // Reaktionen gegeben
        $reactionsGiven = 0;
        try {
            $reactionsGiven = db_count('SELECT COUNT(*) FROM reactions WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Lesezeichen
        $bookmarks = 0;
        try {
            $bookmarks = db_count('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Profil-Aufrufe (falls Spalte existiert)
        $profileViews = 0;
        try {
            $row = db_fetch('SELECT profile_views FROM users WHERE user_id = ?', [$userId]);
            $profileViews = (int) ($row['profile_views'] ?? 0);
        } catch (Exception $e) {}

        return [
            'posts_total'        => $total,
            'posts_week'         => $week,
            'posts_month'        => $month,
            'threads_started'    => $threads,
            'reactions_received' => $reactionsReceived,
            'reactions_given'    => $reactionsGiven,
            'bookmarks'          => $bookmarks,
            'profile_views'      => $profileViews,
        ];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Aktuelle Aktivitäts-Streak: Wie viele Tage in Folge hat der User gepostet?
 */
function dashboard_streak(int $userId): array {
    try {
        // Alle Tage an denen der User gepostet hat (jüngste zuerst)
        $days = db_fetchAll('
            SELECT DATE(created_at) AS day
            FROM posts
            WHERE user_id = ? AND COALESCE(is_approved, 1) = 1 AND deleted_at IS NULL
            GROUP BY DATE(created_at)
            ORDER BY day DESC
            LIMIT 365
        ', [$userId]);

        if (empty($days)) return ['current' => 0, 'longest' => 0, 'today' => false];

        $current = 0;
        $longest = 0;
        $streak  = 0;
        $today   = new DateTime('today');
        $expected = clone $today;
        $todayActive = false;
        $isCurrentStreak = true;

        foreach ($days as $i => $d) {
            $dayDate = new DateTime($d['day']);

            if ($i === 0 && $dayDate == $today) $todayActive = true;

            if ($i === 0) {
                // Erster Eintrag: muss heute oder gestern sein für aktive Streak
                $yesterday = clone $today;
                $yesterday->modify('-1 day');
                if ($dayDate != $today && $dayDate != $yesterday) {
                    $isCurrentStreak = false;
                }
                $streak = 1;
                $expected = clone $dayDate;
                $expected->modify('-1 day');
            } else {
                if ($dayDate == $expected) {
                    $streak++;
                    $expected->modify('-1 day');
                } else {
                    // Streak unterbrochen
                    $longest = max($longest, $streak);
                    if ($isCurrentStreak) {
                        $current = $streak;
                        $isCurrentStreak = false;
                    }
                    // Neue Streak starten
                    $streak = 1;
                    $expected = clone $dayDate;
                    $expected->modify('-1 day');
                }
            }
        }
        $longest = max($longest, $streak);
        if ($isCurrentStreak) $current = $streak;

        return ['current' => $current, 'longest' => $longest, 'today' => $todayActive];
    } catch (Exception $e) {
        return ['current' => 0, 'longest' => 0, 'today' => false];
    }
}

/**
 * Heatmap der letzten 12 Wochen: Wann postet der User?
 * Liefert array mit Tag-Daten (Beitragszahl pro Tag)
 */
function dashboard_heatmap(int $userId): array {
    try {
        $rows = db_fetchAll('
            SELECT DATE(created_at) AS day, COUNT(*) AS cnt
            FROM posts
            WHERE user_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 84 DAY)
              AND COALESCE(is_approved, 1) = 1
              AND deleted_at IS NULL
            GROUP BY DATE(created_at)
        ', [$userId]);

        $map = [];
        foreach ($rows as $r) {
            $map[$r['day']] = (int) $r['cnt'];
        }
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Top 5 eigene Posts (nach Reaktionen)
 */
function dashboard_top_posts(int $userId): array {
    try {
        return db_fetchAll('
            SELECT p.post_id, p.content, p.created_at, p.thread_id,
                   t.title AS thread_title,
                   (SELECT COUNT(*) FROM reactions WHERE post_id = p.post_id) AS reactions
            FROM posts p
            JOIN threads t ON t.thread_id = p.thread_id
            WHERE p.user_id = ?
              AND COALESCE(p.is_approved, 1) = 1
              AND p.deleted_at IS NULL AND t.deleted_at IS NULL
            ORDER BY reactions DESC, p.created_at DESC
            LIMIT 5
        ', [$userId]);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Top 5 eigene Threads (nach Antworten)
 */
function dashboard_top_threads(int $userId): array {
    try {
        return db_fetchAll('
            SELECT t.thread_id, t.title, t.created_at,
                   COALESCE(t.reply_count, 0) AS reply_count
            FROM threads t
            WHERE t.user_id = ?
              AND COALESCE(t.is_approved, 1) = 1
            ORDER BY reply_count DESC, t.created_at DESC
            LIMIT 5
        ', [$userId]);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Top 5 User mit denen am meisten interagiert wird
 * (gemessen an: User schreibt in Threads die der gewählte User gestartet hat, oder umgekehrt)
 */
function dashboard_top_interactions(int $userId): array {
    try {
        return db_fetchAll('
            SELECT u.user_id, u.username, u.avatar,
                   COUNT(*) AS interactions
            FROM posts p
            JOIN threads t ON t.thread_id = p.thread_id
            JOIN users u ON u.user_id = p.user_id
            WHERE t.user_id = ?
              AND p.user_id != ?
              AND u.is_banned = 0
            GROUP BY u.user_id
            ORDER BY interactions DESC
            LIMIT 5
        ', [$userId, $userId]);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Achievement-Status
 */
function dashboard_achievements(int $userId): array {
    $total = 0;
    $unlocked = 0;
    $recent = [];

    try {
        $total = db_count('SELECT COUNT(*) FROM achievements');
    } catch (Exception $e) {
        error_log('[dashboard_achievements] total failed: ' . $e->getMessage());
    }

    try {
        $unlocked = db_count('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?', [$userId]);
    } catch (Exception $e) {
        error_log('[dashboard_achievements] unlocked failed: ' . $e->getMessage());
    }

    try {
        $recent = db_fetchAll('
            SELECT a.achievement_id, a.code, a.title, a.description,
                   COALESCE(a.icon, "🏆") AS icon,
                   ua.earned_at
            FROM user_achievements ua
            JOIN achievements a ON a.achievement_id = ua.achievement_id
            WHERE ua.user_id = ?
            ORDER BY ua.earned_at DESC
            LIMIT 3
        ', [$userId]);
    } catch (Exception $e) {
        error_log('[dashboard_achievements] recent failed: ' . $e->getMessage());
    }

    return [
        'total'    => $total,
        'unlocked' => $unlocked,
        'recent'   => $recent,
    ];
}

/**
 * Letzte Aktivität des Users (Beiträge)
 */
function dashboard_recent_activity(int $userId, int $limit = 5): array {
    try {
        return db_fetchAll('
            SELECT p.post_id, p.created_at, p.thread_id,
                   t.title AS thread_title,
                   COALESCE(p.is_approved, 1) AS is_approved
            FROM posts p
            JOIN threads t ON t.thread_id = p.thread_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
            LIMIT ?
        ', [$userId, $limit]);
    } catch (Exception $e) {
        return [];
    }
}
