<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/ranks.php – Monochromes Rangsystem

$RANKS = [
    ['title' => 'Neuling',              'min_posts' => 0,    'stars' => 1, 'color' => '#6b6b6b'],
    ['title' => 'Mitglied',             'min_posts' => 10,   'stars' => 2, 'color' => '#7a7a7a'],
    ['title' => 'Aktives Mitglied',     'min_posts' => 50,   'stars' => 3, 'color' => '#8a8a8a'],
    ['title' => 'Erfahrenes Mitglied',  'min_posts' => 150,  'stars' => 4, 'color' => '#9a9a9a'],
    ['title' => 'Senior-Mitglied',      'min_posts' => 300,  'stars' => 5, 'color' => '#b0b0b0'],
    ['title' => 'Guru',                 'min_posts' => 600,  'stars' => 6, 'color' => '#c8c8c8'],
    ['title' => 'Legende',              'min_posts' => 1200, 'stars' => 7, 'color' => '#e5e5e5'],
];

function get_rank_for_posts(int $postCount): array {
    global $RANKS;
    $result = $RANKS[0];
    foreach ($RANKS as $rank) {
        if ($postCount >= $rank['min_posts']) $result = $rank;
    }
    return $result;
}

function update_user_rank(int $userId): void {
    $u = db_fetch('SELECT post_count, is_admin, COALESCE(is_owner, 0) AS is_owner FROM users WHERE user_id = ?', [$userId]);
    if (!$u) return;
    // Owner und Admins kriegen einen Sonder-Rang statt der Post-Count-Rang-Logik
    if (!empty($u['is_owner'])) {
        db_execute('UPDATE users SET `rank` = ? WHERE user_id = ?', ['Inhaber', $userId]);
        return;
    }
    if (!empty($u['is_admin'])) {
        db_execute('UPDATE users SET `rank` = ? WHERE user_id = ?', ['Administrator', $userId]);
        return;
    }
    $rank = get_rank_for_posts((int) $u['post_count']);
    db_execute('UPDATE users SET `rank` = ? WHERE user_id = ?', [$rank['title'], $userId]);
}

function recalculate_all_ranks(): int {
    // Owner → "Inhaber"
    db_execute("UPDATE users SET `rank` = 'Inhaber' WHERE COALESCE(is_owner, 0) = 1");
    // Admins → "Administrator" (außer Owner)
    db_execute("UPDATE users SET `rank` = 'Administrator' WHERE is_admin = 1 AND COALESCE(is_owner, 0) = 0");
    // Normale User → Post-Count-basiert
    $users = db_fetchAll('SELECT user_id, post_count FROM users
                         WHERE is_admin = 0 AND COALESCE(is_owner, 0) = 0');
    foreach ($users as $u) {
        $rank = get_rank_for_posts((int) $u['post_count']);
        db_execute('UPDATE users SET `rank` = ? WHERE user_id = ?', [$rank['title'], $u['user_id']]);
    }
    return count($users) + (int) db_count('SELECT COUNT(*) FROM users WHERE is_admin = 1 OR COALESCE(is_owner, 0) = 1');
}

function rank_stars_html(int $count, string $color): string {
    return str_repeat('<span class="star" style="color:' . e($color) . '">★</span>', $count);
}

function rank_badge(string $rankTitle, int $postCount): string {
    $r = get_rank_for_posts($postCount);
    $stars = rank_stars_html($r['stars'], $r['color']);
    return '<span class="rank-title" style="color:' . e($r['color']) . '">'
         . e($rankTitle) . '</span>'
         . '<div class="rank-stars">' . $stars . '</div>';
}
