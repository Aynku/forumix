<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $profile; @var array $overview; @var array $earned; @var int $total; @var int $points */ ?>

<div class="section-heading">
  🏆 Achievements von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $profile['user_id'] ?>"><?= e($profile['username']) ?></a>
</div>

<div class="stat-grid" style="margin-bottom:18px">
  <div class="stat-box">
    <div class="stat-icon">🏅</div>
    <div class="stat-num"><?= count($earned) ?> / <?= $total ?></div>
    <div class="stat-label">Trophäen</div>
  </div>
  <div class="stat-box">
    <div class="stat-icon">⭐</div>
    <div class="stat-num"><?= number_format($points, 0, ',', '.') ?></div>
    <div class="stat-label">Punkte</div>
  </div>
  <div class="stat-box">
    <div class="stat-icon">📊</div>
    <div class="stat-num"><?= $total > 0 ? round(count($earned) / $total * 100) : 0 ?>%</div>
    <div class="stat-label">Fortschritt</div>
  </div>
</div>

<div class="achievements-grid">
  <?php foreach ($overview as $a):
    $isEarned = (bool) $a['earned'];
  ?>
    <div class="achievement-card <?= $isEarned ? 'earned' : 'locked' ?>">
      <div class="achievement-icon"><?= $a['icon'] ?></div>
      <div class="achievement-title"><?= e($a['title']) ?></div>
      <div class="achievement-desc"><?= e($a['description']) ?></div>
      <div class="achievement-meta">
        <span class="achievement-points">+<?= (int) $a['points'] ?> Pkt</span>
        <?php if ($isEarned): ?>
          <span class="achievement-earned">✓ <?= e(format_date($a['earned_at'])) ?></span>
        <?php else: ?>
          <span class="achievement-locked">🔒 Noch nicht</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
