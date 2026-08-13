<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu = current_user();
$dashData = dashboard_data((int) $cu['user_id']);

// Debug-Modus: ?debug=1 (nur Admins) – zeigt Roh-Daten
if (isset($_GET['debug']) && ($cu['is_admin'] || !empty($cu['is_owner']))) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "═══ DASHBOARD-DEBUG ═══\n\n";
    echo "User-ID: " . (int) $cu['user_id'] . "\n\n";
    echo "━━━ dashboard_user() ━━━\n";
    var_export($dashData['user']);
    echo "\n\n━━━ dashboard_overview() ━━━\n";
    var_export($dashData['overview']);
    echo "\n\n━━━ dashboard_achievements() (was die Lib zurückgibt) ━━━\n";
    var_export($dashData['achievements']);
    echo "\n\n━━━ Achievements-Tabelle (direkt geprüft) ━━━\n";
    try {
        $achTotal = db_count('SELECT COUNT(*) FROM achievements');
        echo "Anzahl Achievements gesamt: $achTotal\n";
        $achUser  = db_count('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?', [$cu['user_id']]);
        echo "Davon für dich freigeschaltet: $achUser\n";
        if ($achTotal === 0) {
            echo "\n⚠️  Die achievements-Tabelle ist LEER!\n";
            echo "Lösung: /install/migrations/migrate_v3.php nochmal laufen lassen\n";
        }
    } catch (Exception $e) {
        echo "Fehler: " . $e->getMessage() . "\n";
    }
    exit;
}

// ── Präsentationslogik aus der View hochgezogen: die Template-Engine kann
//    weder DateTime-Schleifen/array_chunk/max() noch strikte stripos()-
//    Vergleiche. Daher hier vorberechnen und fertig ans Template geben. ──

// Heatmap: 12 Wochen × 7 Tage, jede Zelle mit Intensitäts-Level 0–4.
$heatmap  = $dashData['heatmap'] ?? [];
$dashDays = [];
$hmStart  = new DateTime('today');
$hmStart->modify('-83 days');
for ($i = 0; $i < 84; $i++) {
    $key = $hmStart->format('Y-m-d');
    $dashDays[] = ['date' => $key, 'cnt' => $heatmap[$key] ?? 0, 'dow' => (int) $hmStart->format('w')];
    $hmStart->modify('+1 day');
}
$hmMax = 0;
foreach ($dashDays as $d) $hmMax = max($hmMax, $d['cnt']);

$dashWeeks = [];
foreach (array_chunk($dashDays, 7) as $week) {
    foreach ($week as &$cell) {
        $level = 0;
        if ($cell['cnt'] > 0) {
            if ($hmMax <= 1)                       $level = 1;
            elseif ($cell['cnt'] >= $hmMax * 0.75) $level = 4;
            elseif ($cell['cnt'] >= $hmMax * 0.5)  $level = 3;
            elseif ($cell['cnt'] >= $hmMax * 0.25) $level = 2;
            else                                   $level = 1;
        }
        $cell['level'] = $level;
    }
    unset($cell);
    $dashWeeks[] = $week;
}

// Rang ausblenden, wenn er nur die Rolle (Owner/Admin) doppelt sagt.
// stripos() wird strikt mit !== false geprüft (Treffer an Position 0!).
$dashUserData = $dashData['user'] ?? [];
$rangText = trim((string) ($dashUserData['rank'] ?? ''));
$hideRank = false;
if (!empty($dashUserData['is_owner'])
    && (stripos($rangText, 'owner') !== false || stripos($rangText, 'admin') !== false)) {
    $hideRank = true;
} elseif (!empty($dashUserData['is_admin']) && stripos($rangText, 'admin') !== false) {
    $hideRank = true;
}

render('dashboard', [
    'title'        => 'Dein Dashboard',
    'dashUser'     => $dashData['user']             ?? null,
    'dashOverview' => $dashData['overview']         ?? [],
    'dashStreak'   => $dashData['streak']           ?? ['current' => 0, 'longest' => 0, 'today' => false],
    'dashHeatmap'  => $dashData['heatmap']          ?? [],
    'dashWeeks'    => $dashWeeks,
    'rangText'     => $rangText,
    'hideRank'     => $hideRank,
    'topPosts'     => $dashData['top_posts']        ?? [],
    'topThreads'   => $dashData['top_threads']      ?? [],
    'topInteractions' => $dashData['top_interactions'] ?? [],
    'dashAch'      => $dashData['achievements']     ?? ['total' => 0, 'unlocked' => 0, 'recent' => []],
    'recentActivity' => $dashData['recent_activity'] ?? [],
]);
