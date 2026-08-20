<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu = current_user();

// Globaler Mod, Admin, Owner ODER forum-spezifischer Mod → Zugriff
$boardModFor = mod_for_boards((int) $cu['user_id']);
$hasModAccess = $cu['is_admin']
             || $cu['is_mod']
             || !empty($cu['is_owner'])
             || !empty($boardModFor);

if (!$hasModAccess) {
    http_response_code(403);
    render('error', [
        'title' => 'Kein Zugriff',
        'message' => 'Du hast keine Moderator-Rechte.',
    ]);
    return;
}

$action = $_GET['action'] ?? 'dashboard';

// Nur globale Mods/Admins dürfen ALLES sehen, Board-Mods nur ihre Foren
$isGlobalMod = $cu['is_admin'] || $cu['is_mod'] || !empty($cu['is_owner']);
$boardIds = $isGlobalMod ? null : $boardModFor;

// ── AKTIONEN ───────────────────────────────────────────────────────

if (in_array($action, ['approve_thread', 'approve_post', 'reject_thread', 'reject_post'], true)) {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    $id = get_int('id');
    $reason = $_GET['reason'] ?? '';

    // Berechtigungs-Check für Board-Mods
    if (!$isGlobalMod) {
        $isThread = str_contains($action, 'thread');
        $check = $isThread
            ? db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$id])
            : db_fetch('SELECT t.board_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id WHERE p.post_id = ?', [$id]);
        if (!$check || !in_array((int) $check['board_id'], $boardIds, true)) {
            flash('Du bist kein Mod in diesem Forum.', 'error');
            redirect(FORUM_URL . '/mod.php?action=approval');
        }
    }

    $ok = match ($action) {
        'approve_thread' => approve_thread($id),
        'approve_post'   => approve_post($id),
        'reject_thread'  => reject_thread($id, $reason),
        'reject_post'    => reject_post($id, $reason),
    };

    flash($ok ? 'Aktion ausgeführt.' : 'Aktion fehlgeschlagen.', $ok ? 'success' : 'error');
    redirect(FORUM_URL . '/mod.php?action=approval');
}

// Report-Status ändern
if ($action === 'report_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    $reportId = post_int('report_id');
    $newStatus = post_str('status');

    // Dieselbe Board-Pruefung wie bei den Freigaben oben. Die Liste ist zwar
    // gefiltert, die Aktion war es nicht — ein Forum-Mod konnte report_id
    // hochzaehlen und Meldungen aus fremden Foren abhaken.
    if (!$isGlobalMod) {
        $r = db_fetch(
            'SELECT t.board_id FROM reports r
             JOIN posts p   ON p.post_id   = r.post_id
             JOIN threads t ON t.thread_id = p.thread_id
             WHERE r.report_id = ?',
            [$reportId]
        );
        if (!$r || !in_array((int) $r['board_id'], $boardIds, true)) {
            flash('Du bist kein Mod in diesem Forum.', 'error');
            redirect(FORUM_URL . '/mod.php?action=reports');
        }
    }

    if (in_array($newStatus, ['open', 'reviewed', 'dismissed'], true)) {
        try {
            db_execute('UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE report_id = ?',
                [$newStatus, $cu['user_id'], $reportId]);
            flash('Meldung aktualisiert.', 'success');
        } catch (Exception $e) {
            error_log('[mod] report_action: ' . $e->getMessage());
            flash('Meldung konnte nicht aktualisiert werden.', 'error');
        }
    }
    redirect(FORUM_URL . '/mod.php?action=reports');
}

// ── DATEN LADEN ────────────────────────────────────────────────────

$vars = ['action' => $action, 'isGlobalMod' => $isGlobalMod, 'modBoards' => $boardModFor];

switch ($action) {
    case 'dashboard':
        // Stats für Mod-Übersicht
        $vars['stats'] = [
            'pending_threads' => count(pending_threads_filtered($boardIds)),
            'pending_posts'   => count(pending_posts_filtered($boardIds)),
            'open_reports'    => mod_open_reports_count($boardIds),
            'my_mod_boards'   => count($boardModFor),
        ];
        $vars['recentActions'] = mod_recent_actions((int) $cu['user_id'], 10);
        break;

    case 'approval':
        $vars['pendingThreads'] = pending_threads_filtered($boardIds);
        $vars['pendingPosts']   = pending_posts_filtered($boardIds);
        break;

    case 'reports':
        $vars['reports'] = mod_reports_list($boardIds);
        break;

    case 'papierkorb':
        $vars['papierkorbThemen']    = papierkorb_themen($boardIds);
        $vars['papierkorbBeitraege'] = papierkorb_beitraege($boardIds);
        break;

    case 'modlog':
        $vars['logs'] = mod_log_entries((int) $cu['user_id']);
        break;
}

render('mod/index', array_merge(['title' => 'Moderator-Bereich'], $vars));
