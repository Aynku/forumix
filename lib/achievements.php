<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/achievements.php – Trophäen-System

/**
 * Titel und Beschreibung einer Trophaee in der gewaehlten Sprache.
 *
 * Die Texte stehen in der Datenbank — dort hat sie der Installer angelegt,
 * und dort kann sie ein Betreiber aendern. Uebersetzt wird deshalb ueber die
 * Spalte `code`, die jede Trophaee ohnehin traegt: `first_post` wird zu
 * `achv_first_post` im Sprachpaket.
 *
 * Steht dort nichts, bleibt der gespeicherte Text stehen. Das ist der Fall
 * fuer eigene Trophaeen eines Betreibers — die sollen nicht ploetzlich als
 * nackter Schluessel dastehen, nur weil sie im Kern nicht vorgesehen sind.
 *
 * WARUM keine Spalten je Sprache: Dann muesste jede neue Sprache die Tabelle
 * aendern, und ein Betreiber mit eigenen Trophaeen haette leere Felder.
 */
function achievement_uebersetzen(array $a): array {
    foreach (['title' => '_titel', 'description' => '_text'] as $spalte => $endung) {
        if (empty($a['code']) || !isset($a[$spalte])) {
            continue;
        }

        $schluessel = 'achv_' . $a['code'] . $endung;
        $text       = t($schluessel);

        if ($text !== $schluessel) {
            $a[$spalte] = $text;
        }
    }

    return $a;
}

/**
 * Verleiht ein Achievement falls noch nicht vorhanden. Gibt true zurück
 * wenn neu, false wenn bereits vorhanden.
 */
function award_achievement(int $userId, string $code): bool {
    try {
        $a = db_fetch('SELECT achievement_id FROM achievements WHERE code = ?', [$code]);
        if (!$a) return false;
        $exists = db_count(
            'SELECT COUNT(*) FROM user_achievements WHERE user_id = ? AND achievement_id = ?',
            [$userId, $a['achievement_id']]
        );
        if ($exists > 0) return false;
        db_execute(
            'INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)',
            [$userId, $a['achievement_id']]
        );
        return true;
    } catch (Exception $e) { return false; }
}

/**
 * Prüft alle Bedingungen und verleiht passende Trophäen.
 * Aufruf nach jeder relevanten Aktion (Post, Reaktion, Bookmark…).
 * Defensive: alle Fehler werden geschluckt, damit nichts blockiert wird.
 */
function check_achievements(int $userId): array {
    if ($userId <= 0) return [];
    $newOnes = [];

    try {
        $u = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
        if (!$u) return [];

        $postCount = (int) ($u['post_count'] ?? 0);

        // ── Beiträge ──
        if ($postCount >= 1    && award_achievement($userId, 'first_post'))   $newOnes[] = 'first_post';
        if ($postCount >= 10   && award_achievement($userId, 'posts_10'))     $newOnes[] = 'posts_10';
        if ($postCount >= 50   && award_achievement($userId, 'posts_50'))     $newOnes[] = 'posts_50';
        if ($postCount >= 100  && award_achievement($userId, 'posts_100'))    $newOnes[] = 'posts_100';
        if ($postCount >= 500  && award_achievement($userId, 'posts_500'))    $newOnes[] = 'posts_500';
        if ($postCount >= 1000 && award_achievement($userId, 'posts_1000'))   $newOnes[] = 'posts_1000';

        // ── Threads ──
        $threadCount = db_count('SELECT COUNT(*) FROM threads WHERE user_id = ?', [$userId]);
        if ($threadCount >= 1  && award_achievement($userId, 'first_thread')) $newOnes[] = 'first_thread';
        if ($threadCount >= 10 && award_achievement($userId, 'threads_10'))   $newOnes[] = 'threads_10';

        // ── Reaktionen (eigene) ──
        try {
            $myReactions = db_count('SELECT COUNT(*) FROM reactions WHERE user_id = ?', [$userId]);
            if ($myReactions >= 1 && award_achievement($userId, 'first_reaction')) $newOnes[] = 'first_reaction';
        } catch (Exception $e) {}

        // ── Reaktionen (erhalten) ──
        try {
            $received = user_received_reactions($userId);
            if ($received >= 10  && award_achievement($userId, 'received_10'))  $newOnes[] = 'received_10';
            if ($received >= 50  && award_achievement($userId, 'received_50'))  $newOnes[] = 'received_50';
            if ($received >= 100 && award_achievement($userId, 'received_100')) $newOnes[] = 'received_100';
        } catch (Exception $e) {}

        // ── Lesezeichen ──
        try {
            $bm = db_count('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?', [$userId]);
            if ($bm >= 10 && award_achievement($userId, 'bookmarker')) $newOnes[] = 'bookmarker';
        } catch (Exception $e) {}

        // ── Avatar & Signatur ──
        if (!empty($u['avatar'])    && award_achievement($userId, 'avatar_changed')) $newOnes[] = 'avatar_changed';
        if (!empty($u['signature']) && award_achievement($userId, 'signature_set'))  $newOnes[] = 'signature_set';

        // ── Erwähnungen ──
        try {
            $mentioned = db_count(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'mention'",
                [$userId]
            );
            if ($mentioned >= 5 && award_achievement($userId, 'mention_5')) $newOnes[] = 'mention_5';
        } catch (Exception $e) {}

        // ── Umfragen ──
        try {
            $polls = db_count(
                'SELECT COUNT(*) FROM polls p JOIN threads t ON t.thread_id = p.thread_id WHERE t.user_id = ?',
                [$userId]
            );
            if ($polls >= 1 && award_achievement($userId, 'poll_creator')) $newOnes[] = 'poll_creator';
        } catch (Exception $e) {}

        // ── PNs ──
        try {
            $pmsSent = db_count('SELECT COUNT(*) FROM private_messages WHERE sender_id = ?', [$userId]);
            if ($pmsSent >= 1 && award_achievement($userId, 'pm_sent')) $newOnes[] = 'pm_sent';
        } catch (Exception $e) {}

        // ── Jubiläen (1 / 2 Jahre) ──
        if (!empty($u['registered_at'])) {
            $ts = strtotime($u['registered_at']);
            $diff = time() - $ts;
            if ($diff >= 365 * 24 * 3600        && award_achievement($userId, 'birthday_1y')) $newOnes[] = 'birthday_1y';
            if ($diff >= 2 * 365 * 24 * 3600    && award_achievement($userId, 'birthday_2y')) $newOnes[] = 'birthday_2y';
        }

    } catch (Exception $e) {}

    return $newOnes;
}

/**
 * Alle Achievements + Status (verdient/nicht) für User-Profilseite
 */
function user_achievements_overview(int $userId): array {
    try {
        $rows = db_fetchAll('
            SELECT a.*, ua.earned_at,
                   (ua.user_id IS NOT NULL) AS earned
            FROM achievements a
            LEFT JOIN user_achievements ua
              ON ua.achievement_id = a.achievement_id AND ua.user_id = ?
            ORDER BY (ua.user_id IS NULL) ASC, a.points ASC
        ', [$userId]);

        return array_map('achievement_uebersetzen', $rows);
    } catch (Exception $e) { return []; }
}

function user_achievement_count(int $userId): int {
    try {
        return db_count('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?', [$userId]);
    } catch (Exception $e) { return 0; }
}

function user_total_points(int $userId): int {
    try {
        return db_count('
            SELECT IFNULL(SUM(a.points), 0)
            FROM user_achievements ua JOIN achievements a ON a.achievement_id = ua.achievement_id
            WHERE ua.user_id = ?
        ', [$userId]);
    } catch (Exception $e) { return 0; }
}
