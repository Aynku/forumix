<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var array|null $dashUser; @var array $dashOverview; @var array $dashStreak;
   *  @var array $dashHeatmap; @var array $topPosts; @var array $topThreads;
   *  @var array $topInteractions; @var array $dashAch; @var array $recentActivity */
  $u        = $dashUser ?? [];
  $ov       = $dashOverview ?? [];
  $streak   = $dashStreak ?? ['current' => 0, 'longest' => 0, 'today' => false];
  $heatmap  = $dashHeatmap ?? [];
  // $topPosts kommt direkt aus render(), keine Umbenennung nötig
  $topThr   = $topThreads ?? [];
  $topInt   = $topInteractions ?? [];
  $ach      = $dashAch ?? ['total' => 0, 'unlocked' => 0, 'recent' => []];
  $recent   = $recentActivity ?? [];

  // Heatmap-Grid: 12 Wochen × 7 Tage
  $days = [];
  $start = new DateTime('today');
  $start->modify('-83 days');
  for ($i = 0; $i < 84; $i++) {
      $key = $start->format('Y-m-d');
      $days[] = ['date' => $key, 'cnt' => $heatmap[$key] ?? 0, 'dow' => (int) $start->format('w')];
      $start->modify('+1 day');
  }

  // Max für Skalierung
  $maxCnt = 0;
  foreach ($days as $d) $maxCnt = max($maxCnt, $d['cnt']);
?>

<div class="dashboard-hero">
  <div class="dashboard-hero-avatar">
    <?php
      $avatar = !empty($u['avatar']) ? FORUM_URL . '/uploads/avatars/' . $u['avatar'] : FORUM_URL . '/assets/default_avatar.svg';
    ?>
    <img src="<?= e($avatar) ?>" alt="<?= e($u['username'] ?? '') ?>">
  </div>
  <div class="dashboard-hero-info">
    <h1>
      <?= e($u['username'] ?? '–') ?>
      <?php if (!empty($u['is_owner'])): ?>
        <span class="post-owner-badge">👑 OWNER</span>
      <?php elseif (!empty($u['is_admin'])): ?>
        <span class="post-admin-badge">A</span>
      <?php elseif (!empty($u['is_mod'])): ?>
        <span class="post-mod-badge">M</span>
      <?php endif; ?>
    </h1>

    <?php
      // Rang nur anzeigen wenn er nicht redundant zur Rolle ist
      $rangText = trim((string) ($u['rank'] ?? ''));
      $hideRank = false;
      if (!empty($u['is_owner']) && (stripos($rangText, 'owner') !== false || stripos($rangText, 'admin') !== false)) {
          $hideRank = true; // OWNER-Badge sagt schon alles
      } elseif (!empty($u['is_admin']) && stripos($rangText, 'admin') !== false) {
          $hideRank = true; // A-Badge sagt schon alles
      }
    ?>
    <?php if (!$hideRank && $rangText !== ''): ?>
      <div class="dashboard-hero-rank">🏅 <?= e($rangText) ?></div>
    <?php endif; ?>

    <div class="dashboard-hero-meta">
      <span>💬 <strong><?= (int) ($u['post_count'] ?? 0) ?></strong> Beiträge</span>
      <span class="dashboard-hero-sep">·</span>
      <span>📅 Dabei seit <?= e(format_date($u['registered_at'] ?? '')) ?></span>
    </div>
  </div>
  <div class="dashboard-hero-streak">
    <div class="streak-flame <?= $streak['current'] > 0 ? 'streak-active' : '' ?>">
      🔥
    </div>
    <div class="streak-info">
      <div class="streak-num"><?= (int) $streak['current'] ?></div>
      <div class="streak-label">Tag<?= $streak['current'] === 1 ? '' : 'e' ?> Streak</div>
      <?php if ($streak['longest'] > $streak['current']): ?>
        <div class="streak-best">Best: <?= (int) $streak['longest'] ?> 🏆</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php /* ── Übersichts-Stats ── */ ?>
<div class="dashboard-stats">
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) ($ov['posts_total'] ?? 0) ?></div>
    <div class="dash-stat-label">💬 Beiträge gesamt</div>
  </div>
  <div class="dash-stat dash-stat-accent">
    <div class="dash-stat-num"><?= (int) ($ov['posts_week'] ?? 0) ?></div>
    <div class="dash-stat-label">📈 Diese Woche</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) ($ov['posts_month'] ?? 0) ?></div>
    <div class="dash-stat-label">📅 Letzte 30 Tage</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) ($ov['threads_started'] ?? 0) ?></div>
    <div class="dash-stat-label">🧵 Themen gestartet</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) ($ov['reactions_received'] ?? 0) ?></div>
    <div class="dash-stat-label">⭐ Reaktionen erhalten</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) ($ov['reactions_given'] ?? 0) ?></div>
    <div class="dash-stat-label">👍 Reaktionen gegeben</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) ($ov['bookmarks'] ?? 0) ?></div>
    <div class="dash-stat-label">🔖 Lesezeichen</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat-num"><?= (int) $ach['unlocked'] ?>/<?= (int) $ach['total'] ?></div>
    <div class="dash-stat-label">🏆 Achievements</div>
  </div>
</div>

<?php /* ── Heatmap ── */ ?>
<div class="card" style="margin-bottom:18px;padding:20px">
  <h2 style="font-size:15px;margin-bottom:14px">📊 Deine Aktivität – letzte 12 Wochen</h2>
  <div class="heatmap-wrap">
    <div class="heatmap-grid">
      <?php
        $weeks = array_chunk($days, 7);
        foreach ($weeks as $week):
      ?>
        <div class="heatmap-week">
          <?php foreach ($week as $d):
            // Intensitäts-Level 0–4
            $level = 0;
            if ($d['cnt'] > 0) {
              if ($maxCnt <= 1)      $level = 1;
              elseif ($d['cnt'] >= $maxCnt * 0.75) $level = 4;
              elseif ($d['cnt'] >= $maxCnt * 0.5)  $level = 3;
              elseif ($d['cnt'] >= $maxCnt * 0.25) $level = 2;
              else                                 $level = 1;
            }
          ?>
            <div class="heatmap-cell heatmap-l<?= $level ?>"
                 title="<?= e($d['date']) ?>: <?= (int) $d['cnt'] ?> Beitrag<?= $d['cnt'] === 1 ? '' : 'e' ?>"></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="heatmap-legend">
      <span>Weniger</span>
      <div class="heatmap-cell heatmap-l0"></div>
      <div class="heatmap-cell heatmap-l1"></div>
      <div class="heatmap-cell heatmap-l2"></div>
      <div class="heatmap-cell heatmap-l3"></div>
      <div class="heatmap-cell heatmap-l4"></div>
      <span>Mehr</span>
    </div>
  </div>
</div>

<div class="dashboard-cols">
  <?php /* ── Top Posts ── */ ?>
  <div class="card" style="padding:18px">
    <h2 style="font-size:15px;margin-bottom:12px">⭐ Deine Top-Beiträge</h2>
    <?php if (empty($topPosts)): ?>
      <div style="color:var(--text-faint);font-size:13px;font-style:italic">Noch keine Reaktionen erhalten.</div>
    <?php else: ?>
      <div class="dash-list">
        <?php foreach ($topPosts as $p): ?>
          <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $p['thread_id'] ?>#post<?= (int) $p['post_id'] ?>" class="dash-list-item">
            <div class="dash-list-main">
              <div class="dash-list-title"><?= e(mb_substr($p['thread_title'], 0, 60)) ?></div>
              <div class="dash-list-snippet"><?= e(mb_substr(strip_tags($p['content']), 0, 80)) ?>…</div>
            </div>
            <div class="dash-list-count">⭐ <?= (int) $p['reactions'] ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php /* ── Top Threads ── */ ?>
  <div class="card" style="padding:18px">
    <h2 style="font-size:15px;margin-bottom:12px">🧵 Deine erfolgreichsten Themen</h2>
    <?php if (empty($topThr)): ?>
      <div style="color:var(--text-faint);font-size:13px;font-style:italic">Noch keine Themen gestartet.</div>
    <?php else: ?>
      <div class="dash-list">
        <?php foreach ($topThr as $t): ?>
          <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $t['thread_id'] ?>" class="dash-list-item">
            <div class="dash-list-main">
              <div class="dash-list-title"><?= e(mb_substr($t['title'], 0, 60)) ?></div>
              <div class="dash-list-snippet">vor <?= e(format_date($t['created_at'])) ?></div>
            </div>
            <div class="dash-list-count">💬 <?= (int) $t['reply_count'] ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php /* ── Top Interaktionen ── */ ?>
  <div class="card" style="padding:18px">
    <h2 style="font-size:15px;margin-bottom:12px">👥 Mit wem interagierst du am meisten?</h2>
    <?php if (empty($topInt)): ?>
      <div style="color:var(--text-faint);font-size:13px;font-style:italic">
        Noch keine Interaktionen.
      </div>
    <?php else: ?>
      <div class="dash-list">
        <?php foreach ($topInt as $u2): ?>
          <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u2['user_id'] ?>" class="dash-list-item">
            <img src="<?= !empty($u2['avatar']) ? e(FORUM_URL . '/uploads/avatars/' . $u2['avatar']) : e(FORUM_URL . '/assets/default_avatar.svg') ?>"
                 alt="" class="dash-list-avatar">
            <div class="dash-list-main">
              <div class="dash-list-title"><?= e($u2['username']) ?></div>
              <div class="dash-list-snippet"><?= (int) $u2['interactions'] ?> Beiträge in deinen Themen</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php /* ── Achievements ── */ ?>
  <div class="card" style="padding:18px">
    <h2 style="font-size:15px;margin-bottom:12px">
      🏆 Achievements
      <a href="<?= e(FORUM_URL) ?>/achievements.php" style="font-size:12px;font-weight:400;margin-left:6px;color:var(--text-muted)">alle anzeigen →</a>
    </h2>
    <div class="dash-ach-progress">
      <div class="dash-ach-progress-bar" style="width:<?= $ach['total'] > 0 ? (int) ($ach['unlocked'] / $ach['total'] * 100) : 0 ?>%"></div>
    </div>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
      <?= (int) $ach['unlocked'] ?> von <?= (int) $ach['total'] ?> freigeschaltet
    </div>
    <?php if (!empty($ach['recent'])): ?>
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Zuletzt freigeschaltet</div>
      <div class="dash-list">
        <?php foreach ($ach['recent'] as $a): ?>
          <div class="dash-list-item" style="cursor:default">
            <div style="font-size:24px;flex-shrink:0"><?= e($a['icon'] ?? '🏆') ?></div>
            <div class="dash-list-main">
              <div class="dash-list-title"><?= e($a['title']) ?></div>
              <div class="dash-list-snippet"><?= e($a['description']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php /* ── Recent Activity ── */ ?>
<div class="card" style="padding:18px;margin-top:18px">
  <h2 style="font-size:15px;margin-bottom:12px">📝 Deine letzte Aktivität</h2>
  <?php if (empty($recent)): ?>
    <div style="color:var(--text-faint);font-size:13px;font-style:italic">Noch keine Aktivität.</div>
  <?php else: ?>
    <div class="dash-list">
      <?php foreach ($recent as $r): ?>
        <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $r['thread_id'] ?>#post<?= (int) $r['post_id'] ?>" class="dash-list-item">
          <div style="font-size:18px;flex-shrink:0">💬</div>
          <div class="dash-list-main">
            <div class="dash-list-title">
              <?= e($r['thread_title']) ?>
              <?php if (empty($r['is_approved'])): ?>
                <span style="color:var(--color-warning);font-size:11px;margin-left:6px">⏳ wartet auf Freigabe</span>
              <?php endif; ?>
            </div>
            <div class="dash-list-snippet">vor <?= e(format_date($r['created_at'])) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
