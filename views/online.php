<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $users; @var array $stats */ ?>

<div class="section-heading">🟢 Wer ist online</div>

<div class="stat-grid" style="margin-bottom:18px">
  <div class="stat-box">
    <div class="stat-icon">👥</div>
    <div class="stat-num"><?= (int) $stats['total'] ?></div>
    <div class="stat-label">Gesamt online</div>
  </div>
  <div class="stat-box">
    <div class="stat-icon">👤</div>
    <div class="stat-num"><?= (int) $stats['members'] ?></div>
    <div class="stat-label">Mitglieder</div>
  </div>
  <div class="stat-box">
    <div class="stat-icon">👻</div>
    <div class="stat-num"><?= (int) $stats['guests'] ?></div>
    <div class="stat-label">Gäste</div>
  </div>
  <div class="stat-box">
    <div class="stat-icon">🤖</div>
    <div class="stat-num"><?= (int) ($stats['bots'] ?? 0) ?></div>
    <div class="stat-label">Bots</div>
  </div>
</div>

<div class="card">
  <table class="forum-table">
    <thead>
      <tr><th>Benutzer</th><th>Ort</th><th>Zuletzt gesehen</th><?php if ($isMod): ?><th>IP</th><?php endif; ?></tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
        <tr><td colspan="<?= $isMod ? 4 : 3 ?>" style="text-align:center;padding:20px;color:var(--text-faint)">
          Niemand online.
        </td></tr>
      <?php endif; ?>
      <?php foreach ($users as $u): ?>
        <?php $bot = !$u['user_id'] ? detect_bot($u['user_agent'] ?? null) : null; ?>
        <tr<?= $bot ? ' class="online-bot-row"' : '' ?>>
          <td>
            <?php if ($u['user_id']): ?>
              <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>">
                <?= e($u['username']) ?>
              </a>
              <?php if ($u['is_banned']): ?>
                <span class="post-banned-badge" style="margin-left:4px">🚫</span>
              <?php elseif ($u['is_admin']): ?>
                <span class="post-admin-badge" style="margin-left:4px">A</span>
              <?php elseif ($u['is_mod']): ?>
                <span class="post-mod-badge" style="margin-left:4px">M</span>
              <?php endif; ?>
            <?php elseif ($bot): ?>
              <span class="online-bot" title="<?= e($u['user_agent'] ?? '') ?>">
                <?= $bot['icon'] ?> <?= e($bot['name']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-muted)">👻 Gast</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted)"><?= e($u['location']) ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($u['last_seen'])) ?></td>
          <?php if ($isMod): ?>
            <td><code style="font-size:11px"><?= e($u['ip'] ?? '–') ?></code></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
