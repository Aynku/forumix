<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $team; @var array $members; @var array $pag; @var string $pagination; @var string $sort; @var int $totalUsers */ ?>

<div class="section-heading">
  👥 Mitglieder
  <span style="font-size:12px;color:var(--text-muted);font-weight:400;text-transform:none">
    <?= number_format((int) $totalUsers, 0, ',', '.') ?> registriert
  </span>
</div>

<?php /* ════════════ TEAM ════════════ */ ?>
<?php if (!empty($team['owners']) || !empty($team['admins']) || !empty($team['mods']) || !empty($team['board_mods'])): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-header">
    <span class="card-title">🛡️ Das Team</span>
  </div>

  <?php /* Owner – kann mehrere geben */ ?>
  <?php if (!empty($team['owners'])): ?>
    <div class="team-section">
      <div class="team-section-label" style="color:#7c3aed">
        👑 <?= count($team['owners']) > 1 ? 'Inhaber:innen' : 'Inhaber' ?>
      </div>
      <div class="team-grid">
        <?php foreach ($team['owners'] as $u): ?>
          <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>" class="team-card">
            <img src="<?= e(avatar_url($u)) ?>" alt="" class="team-avatar zoomable-avatar" width="48" height="48"
                 data-username="<?= e($u['username']) ?>"
                 data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>">
            <div class="team-info">
              <div class="team-name">
                <?= online_dot($u) ?>
                <strong style="color:#7c3aed"><?= e($u['username']) ?></strong>
              </div>
              <div class="team-meta">
                📝 <?= (int) $u['post_count'] ?> &middot;
                📅 seit <?= e(date('m/Y', strtotime($u['registered_at']))) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php /* Admins */ ?>
  <?php if (!empty($team['admins'])): ?>
    <div class="team-section">
      <div class="team-section-label" style="color:#dc2626">🔴 Administratoren</div>
      <div class="team-grid">
        <?php foreach ($team['admins'] as $u): ?>
          <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>" class="team-card">
            <img src="<?= e(avatar_url($u)) ?>" alt="" class="team-avatar zoomable-avatar" width="48" height="48" data-username="<?= e($u['username']) ?>" data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>">
            <div class="team-info">
              <div class="team-name">
                <?= online_dot($u) ?>
                <strong style="color:#dc2626"><?= e($u['username']) ?></strong>
              </div>
              <div class="team-meta">
                📝 <?= (int) $u['post_count'] ?> &middot;
                📅 seit <?= e(date('m/Y', strtotime($u['registered_at']))) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php /* Globale Mods */ ?>
  <?php if (!empty($team['mods'])): ?>
    <div class="team-section">
      <div class="team-section-label" style="color:#f59e0b">🟠 Globale Moderatoren</div>
      <div class="team-grid">
        <?php foreach ($team['mods'] as $u): ?>
          <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>" class="team-card">
            <img src="<?= e(avatar_url($u)) ?>" alt="" class="team-avatar zoomable-avatar" width="48" height="48" data-username="<?= e($u['username']) ?>" data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>">
            <div class="team-info">
              <div class="team-name">
                <?= online_dot($u) ?>
                <strong style="color:#f59e0b"><?= e($u['username']) ?></strong>
              </div>
              <div class="team-meta">
                📝 <?= (int) $u['post_count'] ?> &middot;
                📅 seit <?= e(date('m/Y', strtotime($u['registered_at']))) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php /* Forum-Mods */ ?>
  <?php if (!empty($team['board_mods'])): ?>
    <div class="team-section">
      <div class="team-section-label" style="color:#f59e0b">🟡 Forum-Moderatoren</div>
      <div class="team-grid">
        <?php foreach ($team['board_mods'] as $u): ?>
          <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>" class="team-card">
            <img src="<?= e(avatar_url($u)) ?>" alt="" class="team-avatar zoomable-avatar" width="48" height="48" data-username="<?= e($u['username']) ?>" data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>">
            <div class="team-info">
              <div class="team-name">
                <?= online_dot($u) ?>
                <strong style="color:#f59e0b"><?= e($u['username']) ?></strong>
              </div>
              <div class="team-meta">
                <?php
                  $titles = array_map(fn($b) => $b['title'], $u['boards']);
                ?>
                🛡️ <?= e(implode(', ', $titles)) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ════════════ ALLE MITGLIEDER ════════════ */ ?>
<div class="section-heading">
  Alle Mitglieder
  <span>
    <a href="?sort=posts" class="btn btn-ghost btn-xs <?= $sort === 'posts' ? 'btn-primary' : '' ?>">📝 Aktivste</a>
    <a href="?sort=newest" class="btn btn-ghost btn-xs <?= $sort === 'newest' ? 'btn-primary' : '' ?>">🆕 Neueste</a>
    <a href="?sort=oldest" class="btn btn-ghost btn-xs <?= $sort === 'oldest' ? 'btn-primary' : '' ?>">📅 Älteste</a>
    <a href="?sort=name" class="btn btn-ghost btn-xs <?= $sort === 'name' ? 'btn-primary' : '' ?>">🔤 Name</a>
    <a href="?sort=active" class="btn btn-ghost btn-xs <?= $sort === 'active' ? 'btn-primary' : '' ?>">⏰ Zuletzt aktiv</a>
  </span>
</div>

<div class="card">
  <table class="forum-table">
    <thead>
      <tr>
        <th>Mitglied</th>
        <th>Rang</th>
        <th style="text-align:center;width:90px">Beiträge</th>
        <th style="width:120px">Dabei seit</th>
        <th style="width:140px">Zuletzt aktiv</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($members as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <img src="<?= e(avatar_url($u)) ?>" alt="" class="member-list-avatar zoomable-avatar" width="32" height="32" data-username="<?= e($u['username']) ?>" data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>">
              <div>
                <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>" style="font-weight:600">
                  <?= online_dot($u) ?>
                  <?= e($u['username']) ?>
                </a>
                <?php if (!empty($u['is_owner'])): ?>
                  <span class="member-role-tag" style="background:#7c3aed">Owner</span>
                <?php elseif ($u['is_admin']): ?>
                  <span class="member-role-tag" style="background:#dc2626">Admin</span>
                <?php elseif ($u['is_mod']): ?>
                  <span class="member-role-tag" style="background:#f59e0b">Mod</span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:12px;color:var(--text-muted)"><?= e($u['rank'] ?? '–') ?></td>
          <td class="stat-cell"><?= number_format((int) $u['post_count'], 0, ',', '.') ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= e(date('d.m.Y', strtotime($u['registered_at']))) ?></td>
          <td style="font-size:12px;color:var(--text-muted)">
            <?= $u['last_login'] ? e(format_date($u['last_login'])) : '<em>nie</em>' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $pagination ?>
