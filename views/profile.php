<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $profile; @var array $latestPosts; @var bool $isOwner */ ?>

<?php
  $achCount = user_achievement_count((int) $profile['user_id']);
  $achPts   = user_total_points((int) $profile['user_id']);
  $received = user_received_reactions((int) $profile['user_id']);
?>

<!-- ── Hero: Banner + Avatar + Info ─────────────────────────── -->
<?php if (!empty($profile['profile_banner'])): ?>
<div class="profile-banner" style="background-image:url('<?= e(FORUM_URL) ?>/uploads/avatars/<?= e(rawurlencode($profile['profile_banner'])) ?>')"></div>
<?php endif; ?>

<div class="profile-hero <?= !empty($profile['profile_banner']) ? 'with-banner' : '' ?>">
  <img class="profile-hero-avatar zoomable-avatar"
       src="<?= e(avatar_url($profile)) ?>"
       alt="<?= e($profile['username']) ?>"
       data-username="<?= e($profile['username']) ?>">

  <div class="profile-hero-info">
    <!-- Username + Online-Dot + Badge -->
    <div class="profile-username-row">
      <h2 class="profile-username">
        <?= online_dot($profile) ?>
        <?= e($profile['username']) ?>
      </h2>
      <div class="profile-badge-row">
        <?php if (!empty($profile['is_owner'])): ?>
          <span class="post-admin-badge" style="background:#7c3aed !important">👑 Owner</span>
        <?php elseif (!empty($profile['is_banned'])): ?>
          <span class="post-banned-badge">Gesperrt</span>
        <?php elseif ($profile['is_admin']): ?>
          <span class="post-admin-badge">Admin</span>
        <?php elseif ($profile['is_mod']): ?>
          <span class="post-mod-badge">Mod</span>
        <?php else: ?>
          <?= rank_badge($profile['rank'], (int) $profile['post_count']) ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats-Grid (2x3 statt linearer Liste) -->
    <div class="profile-stats-grid">
      <div class="profile-stat-card">
        <span class="profile-stat-icon">📝</span>
        <div class="profile-stat-content">
          <strong><?= (int) $profile['post_count'] ?></strong>
          <span>Beiträge</span>
        </div>
      </div>
      <div class="profile-stat-card">
        <span class="profile-stat-icon">👍</span>
        <div class="profile-stat-content">
          <strong><?= $received ?></strong>
          <span>Reaktionen</span>
        </div>
      </div>
      <a href="<?= e(FORUM_URL) ?>/achievements.php?user=<?= (int) $profile['user_id'] ?>" class="profile-stat-card profile-stat-clickable">
        <span class="profile-stat-icon">🏆</span>
        <div class="profile-stat-content">
          <strong><?= $achCount ?></strong>
          <span>Achievements</span>
        </div>
      </a>
      <div class="profile-stat-card">
        <span class="profile-stat-icon">⭐</span>
        <div class="profile-stat-content">
          <strong><?= $achPts ?></strong>
          <span>Punkte</span>
        </div>
      </div>
    </div>

    <!-- Meta-Infos (kleiner Text) -->
    <div class="profile-meta">
      📅 Dabei seit <strong><?= e(date('d.m.Y', strtotime($profile['registered_at']))) ?></strong>
      <?php if (!empty($profile['last_login'])): ?>
        · 🕐 Zuletzt aktiv <strong><?= e(format_date($profile['last_login'])) ?></strong>
      <?php endif; ?>
    </div>

    <!-- Action-Buttons -->
    <div class="profile-actions">
      <?php if ($currentUser && $currentUser['user_id'] != $profile['user_id']): ?>
        <a href="<?= e(FORUM_URL) ?>/pm.php?new=1&to=<?= (int) $profile['user_id'] ?>" class="btn btn-primary btn-sm">
          📬 PN senden
        </a>
      <?php endif; ?>
      <?php if ($currentUser && $currentUser['user_id'] == $profile['user_id']): ?>
        <a href="<?= e(FORUM_URL) ?>/settings.php" class="btn btn-primary btn-sm">
          ⚙️ Profil bearbeiten
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Erweiterte Profil-Felder (v0.18.31) ──────────────────── -->
<?php
  $visibleFields = profile_visible_fields($profile, $currentUser);
  if (!empty($visibleFields)):
?>
<div class="section-heading">👤 Über <?= e($profile['username']) ?></div>
<div class="card" style="margin-bottom:16px;padding:14px 16px">
  <div class="profile-fields-grid">
    <?php foreach ($visibleFields as $key => $field): ?>
      <?php
        // "Über mich" und "Interessen" sind länger → 2-Spalten-Breite
        $isWide = in_array($key, ['about', 'interests'], true);
      ?>
      <div class="profile-field-card" <?= $isWide ? 'style="grid-column:1 / -1"' : '' ?>>
        <span class="profile-field-card-icon"><?= $field['icon'] ?></span>
        <div class="profile-field-card-content">
          <div class="profile-field-card-label"><?= e($field['label']) ?></div>
          <div class="profile-field-card-value <?= $field['type'] === 'textarea' ? 'long-text' : '' ?>">
            <?php if ($field['type'] === 'textarea'): ?>
              <?= bbcode($field['value']) ?>
            <?php else: ?>
              <?= e($field['value']) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Signatur ─────────────────────────────────────────────── -->
<?php if (!empty($profile['signature'])): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header"><div class="card-title">✍️ Signatur</div></div>
  <div class="card-body" style="padding:14px 16px;font-size:13px">
    <?= bbcode($profile['signature']) ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Letzte Beiträge ──────────────────────────────────────── -->
<div class="section-heading">📰 Letzte Beiträge</div>
<div class="card" style="margin-bottom:16px">
  <table class="forum-table">
    <thead><tr><th>Thread</th><th style="width:180px">Datum</th></tr></thead>
    <tbody>
      <?php if (empty($latestPosts)): ?>
        <tr><td colspan="2" style="text-align:center;color:var(--text-faint);padding:20px">
          <?php if ($isOwner): ?>
            Du hast noch keine Beiträge geschrieben. Worauf wartest du? 😊
          <?php else: ?>
            Noch keine Beiträge.
          <?php endif; ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($latestPosts as $lp): ?>
      <tr>
        <td>
          <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $lp['thread_id'] ?>#post<?= (int) $lp['post_id'] ?>">
            <?= e($lp['thread_title']) ?>
          </a>
        </td>
        <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($lp['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ── IP-Verlauf (nur Mods/Admins, ans Ende, ausklappbar) ──── -->
<?php if ($isMod && !empty($ipHistory)):
    $ipCount = count($ipHistory);
    $initialLimit = 10;
    $hasMore = $ipCount > $initialLimit;
?>
<details class="mod-ip-history" <?= !$hasMore ? 'open' : '' ?>>
  <summary class="mod-ip-summary">
    <span>🔍 IP-Verlauf <span style="color:var(--text-faint);font-weight:normal">(nur für Moderatoren · <?= $ipCount ?> Einträge)</span></span>
    <span class="mod-ip-summary-chevron">▼</span>
  </summary>

  <div class="card" style="margin-top:8px">
    <div style="padding:10px 14px;font-size:12px;color:var(--text-muted);background:var(--bg-surface2);border-bottom:1px solid var(--border)">
      Registrierungs-IP: <code><?= e($profile['registration_ip'] ?? '–') ?></code>
      &nbsp;·&nbsp;
      Letzte IP: <code><?= e($profile['last_ip'] ?? '–') ?></code>
    </div>
    <table class="forum-table">
      <thead><tr><th>IP-Adresse</th><th>Aktion</th><th>Zuletzt gesehen</th><th>Anzahl</th><th>Aktionen</th></tr></thead>
      <tbody>
        <?php foreach ($ipHistory as $idx => $h):
            $hidden = $hasMore && $idx >= $initialLimit;
        ?>
        <tr class="ip-row <?= $hidden ? 'ip-row-hidden' : '' ?>" <?= $hidden ? 'style="display:none"' : '' ?>>
          <td><code><?= e($h['ip']) ?></code></td>
          <td><?php
            $icons = ['register' => '📝 Registriert', 'login' => '🔑 Login', 'post' => '📨 Post'];
            echo $icons[$h['action']] ?? e($h['action']);
          ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($h['last_seen'])) ?></td>
          <td><?= (int) $h['hits'] ?></td>
          <td>
            <?php if ($isAdmin): ?>
              <a href="<?= e(FORUM_URL) ?>/admin/?action=ban_ip&ip=<?= e(urlencode($h['ip'])) ?>&csrf=<?= e($csrfToken) ?>"
                 class="btn btn-danger btn-xs"
                 onclick="return confirm('IP <?= e($h['ip']) ?> sperren?')">🚫 IP sperren</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($hasMore): ?>
      <div style="padding:10px 14px;border-top:1px solid var(--border);text-align:center;background:var(--bg-surface2)">
        <button type="button" class="btn btn-ghost btn-sm" onclick="
          this.closest('.card').querySelectorAll('.ip-row-hidden').forEach(r => r.style.display = '');
          this.style.display = 'none';
        ">▼ <?= $ipCount - $initialLimit ?> weitere Einträge anzeigen</button>
      </div>
    <?php endif; ?>
  </div>
</details>
<?php endif; ?>
