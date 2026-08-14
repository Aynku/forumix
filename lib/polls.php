<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/polls.php

function get_poll_for_thread(int $threadId): ?array {
    try {
        $poll = db_fetch('SELECT * FROM polls WHERE thread_id = ?', [$threadId]);
        if (!$poll) return null;
        $poll['options'] = db_fetchAll(
            'SELECT * FROM poll_options WHERE poll_id = ? ORDER BY position, option_id',
            [$poll['poll_id']]
        );
        $poll['total_votes'] = array_sum(array_column($poll['options'], 'vote_count'));
        return $poll;
    } catch (Exception $e) { return null; }
}

function user_has_voted(int $pollId, int $userId): bool {
    try {
        return db_count('SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?', [$pollId, $userId]) > 0;
    } catch (Exception $e) { return false; }
}

function create_poll(int $threadId, string $question, array $options, bool $multi = false): bool {
    if (mb_strlen($question) < 3) return false;
    $options = array_filter(array_map('trim', $options), fn($o) => mb_strlen($o) >= 1);
    if (count($options) < 2) return false;

    try {
        $pollId = db_execute(
            'INSERT INTO polls (thread_id, question, multi_choice) VALUES (?, ?, ?)',
            [$threadId, $question, $multi ? 1 : 0]
        );
        foreach (array_values($options) as $i => $text) {
            db_execute(
                'INSERT INTO poll_options (poll_id, text, position) VALUES (?, ?, ?)',
                [$pollId, mb_substr($text, 0, 200), $i]
            );
        }
        return true;
    } catch (Exception $e) { return false; }
}

/**
 * Gibt Stimmen ab. $threadId ist der Thread, aus dem das Formular kam — die
 * Umfrage muss dazu gehoeren. Ohne diese Bindung genuegte ein harmloser
 * oeffentlicher Thread, um mit fremder poll_id in einer Umfrage abzustimmen,
 * die man gar nicht sehen darf.
 */
function cast_vote(int $pollId, array $optionIds, int $userId, ?int $threadId = null): bool {
    $poll = db_fetch('SELECT * FROM polls WHERE poll_id = ?', [$pollId]);
    if (!$poll) return false;
    if ($threadId !== null && (int) $poll['thread_id'] !== $threadId) return false;
    if ($poll['expires_at'] && strtotime($poll['expires_at']) < time()) return false;
    if (user_has_voted($pollId, $userId)) return false;
    if (!$poll['multi_choice']) $optionIds = [$optionIds[0] ?? null];

    foreach ($optionIds as $optId) {
        $optId = (int) $optId;
        if ($optId <= 0) continue;
        $valid = db_fetch('SELECT option_id FROM poll_options WHERE option_id = ? AND poll_id = ?', [$optId, $pollId]);
        if (!$valid) continue;
        try {
            db_execute('INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)', [$pollId, $optId, $userId]);
            db_execute('UPDATE poll_options SET vote_count = vote_count + 1 WHERE option_id = ?', [$optId]);
        } catch (Exception $e) {}
    }
    return true;
}
