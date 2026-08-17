<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $categories; @var array $boardMods */ ?>

<?php /* ── MODULE: Hooks oben (z.B. Shoutbox) ── */ ?>
<?php render_module_hook('home_top'); ?>

<?php foreach ($categories as $cat): ?>
  <div class="category-label"><?= e($cat['title']) ?></div>
  <?php $zeigeMods = boards_have_mods($cat['boards'], $boardMods); ?>
  <div class="card">
    <?php /* Ohne Kopfzeile: die Zahlen tragen ihre Bezeichnung selbst. */ ?>
    <table class="forum-table forum-table-responsive">
      <tbody>
        <?php if (empty($cat['boards'])): ?>
          <tr>
            <td colspan="4" style="text-align:center;color:var(--text-faint);padding:20px">
              Noch keine Foren in dieser Kategorie.
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($cat['boards'] as $b): ?>
        <?php $bIsLocked = !empty($b['password_hash']) && !board_user_can_access($b); ?>
        <tr class="forum-row">
          <td class="forum-icon-cell" style="width:48px">
            <?php if ($bIsLocked): ?>
              <div class="board-icon-wrap is-locked" title="Gesperrt">🔒</div>
            <?php else: ?>
              <div class="board-icon-wrap"><?= e(board_initials($b['title'])) ?></div>
            <?php endif; ?>
          </td>
          <td class="forum-main-cell">
            <a href="<?= e(FORUM_URL) ?>/board.php?id=<?= (int) $b['board_id'] ?>" class="board-title-link">
              <?php if (!empty($b['password_hash'])): ?>
                <span title="Passwort-geschützt" style="color:var(--color-accent);font-size:12px">🔒</span>
              <?php endif; ?>
              <?= e($b['title']) ?>
            </a>
            <?php if (!empty($b['description'])): ?>
              <div class="board-desc"><?= e($b['description']) ?></div>
            <?php endif; ?>
          </td>
          <td class="stat-cell">
            <b><?= number_format((int) $b['thread_count'], 0, ',', '.') ?></b>
            <span class="stat-label">Themen</span><br>
            <b><?= number_format((int) $b['post_count'], 0, ',', '.') ?></b>
            <span class="stat-label">Beiträge</span>
          </td>
          <td class="last-post-cell" data-label="Letzter Beitrag">
            <?php if (!empty($b['last_post_date'])): ?>
              <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $b['last_thread_id'] ?>">
                <?= e(truncate($b['last_thread_title'] ?? '...', 44)) ?>
              </a>
              <span class="last-post-meta">
                von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $b['last_user_id'] ?>"><?= e($b['last_username']) ?></a>
                · <?= e(format_date($b['last_post_date'])) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-faint)">Noch kein Beitrag</span>
            <?php endif; ?>
          </td>
          <?php if ($zeigeMods): ?>
          <td class="mod-cell" data-label="Mods">
            <?php
              $mods = $boardMods[(int) $b['board_id']] ?? [];
              if (empty($mods)):
            ?>
              <span style="color:var(--text-faint);font-size:11px;font-style:italic">–</span>
            <?php else: ?>
              <div class="board-mods-list">
                <?php foreach ($mods as $m):
                  $role = user_role_style($m);
                ?>
                  <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $m['user_id'] ?>"
                     style="color:<?= $role['color'] ?>;font-weight:600"
                     title="<?= e($role['title'] ?: 'Moderator') ?>"><?= e($m['username']) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>

<?php if (!empty($showStatsBox)): ?>
<div class="info-boxes">

  <?php /* ── BOX: Wer ist online ── */ ?>
  <div class="card info-box">
    <div class="card-header">
      <span class="card-title">
        <a href="<?= e(FORUM_URL) ?>/online.php" style="color:inherit">Wer ist online?</a>
      </span>
      <span style="font-size:11px;color:var(--text-muted)">
        <a href="<?= e(FORUM_URL) ?>/online.php" style="color:var(--text-muted)"><?= (int) $onlineStats['total'] ?> insgesamt →</a>
      </span>
    </div>
    <div style="padding:12px 16px">
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">
        <?= (int) $onlineStats['members'] ?> Mitglieder &middot;
        <?= (int) $onlineStats['guests'] ?> Gäste<?php if (!empty($onlineStats['bots'])): ?> &middot;
        <?= (int) $onlineStats['bots'] ?> Bots<?php endif; ?>
      </div>
      <?php if (empty($onlineList)): ?>
        <p style="color:var(--text-faint);font-size:12px;font-style:italic">Niemand online.</p>
      <?php else: ?>
        <div class="online-users">
          <?php
          $namedUsers = array_filter($onlineList, fn($u) => !empty($u['user_id']));
          $first = true;
          foreach ($namedUsers as $u):
            if (!$first) echo ', ';
            $first = false;
            $role = user_role_style($u);
          ?>
            <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"
               style="color:<?= $role['color'] ?>;font-weight:<?= $role['weight'] ?>"
               title="<?= e($role['title']) ?>"><?= e($u['username']) ?></a>
          <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:var(--text-faint);margin-top:10px;border-top:1px dashed var(--border);padding-top:8px">
          <span style="color:#7c3aed;font-weight:600">Inhaber</span>,
          <span style="color:#dc2626;font-weight:600">Administrator</span>,
          <span style="color:#f59e0b;font-weight:600">Moderator</span>,
          Mitglied
        </p>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ── BOX: Forum-Statistiken ── */ ?>
  <div class="card info-box">
    <div class="card-header">
      <span class="card-title">Forum-Statistik</span>
    </div>
    <div style="padding:12px 16px;font-size:13px;line-height:1.9">
      <div>
        <strong><?= number_format((int) $forumStats['threads'], 0, ',', '.') ?></strong> Themen
        &middot;
        <strong><?= number_format((int) $forumStats['posts'], 0, ',', '.') ?></strong> Beiträge
      </div>
      <div>
        <strong><?= number_format((int) $forumStats['members'], 0, ',', '.') ?></strong> Mitglieder
      </div>
      <?php if (!empty($forumStats['newest'])): ?>
        <div>
          Neuestes Mitglied:
          <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $forumStats['newest']['user_id'] ?>">
            <strong><?= e($forumStats['newest']['username']) ?></strong>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php endif; ?>
