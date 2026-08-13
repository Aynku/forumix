<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $board; @var array $threads; @var string $pagination */ ?>

<div class="section-heading">
  <?= e($board['title']) ?>
  <span style="display:inline-flex;gap:6px">
    <a href="<?= e(FORUM_URL) ?>/rss.php?type=board&id=<?= (int) $board['board_id'] ?>"
       class="btn btn-ghost btn-sm" title="RSS-Feed">📰</a>
    <?php if ($currentUser): ?>
      <a href="<?= e(FORUM_URL) ?>/mark_all_read.php?csrf=<?= e($csrfToken) ?>"
         class="btn btn-ghost btn-sm" title="Alle als gelesen">✓</a>
      <a href="<?= e(FORUM_URL) ?>/new_thread.php?board=<?= (int) $board['board_id'] ?>"
         class="btn btn-primary btn-sm">+ Neues Thema</a>
    <?php endif; ?>
  </span>
</div>

<div class="card">
  <table class="forum-table thread-table-responsive">
    <thead>
      <tr>
        <th colspan="2">Thema</th>
        <th style="text-align:center;width:90px">Antworten</th>
        <th style="text-align:center;width:80px">Aufrufe</th>
        <th style="width:210px">Letzter Beitrag</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($threads)): ?>
        <tr>
          <td colspan="5" style="text-align:center;padding:30px;color:var(--text-faint)">
            Noch keine Themen in diesem Forum.
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($threads as $t):
        $tid = (int) $t['thread_id'];
        $isUnread = $unreadStatus[$tid] ?? false;
      ?>
      <tr class="thread-row<?= $isUnread ? ' thread-unread' : '' ?>">
        <td class="thread-icon-cell" style="width:48px">
          <div class="thread-icon-wrap <?= $t['is_sticky'] ? 'sticky' : ($t['is_closed'] ? 'closed' : '') ?>">
            <?= $t['is_closed'] ? '🔒' : ($t['is_sticky'] ? '📌' : '💬') ?>
          </div>
        </td>
        <td class="thread-main-cell">
          <div>
            <?php if ($isUnread): ?><span class="unread-dot" title="Ungelesen"></span><?php endif; ?>
            <?php if ($t['is_sticky']): ?><span class="badge badge-sticky">Angepinnt</span><?php endif; ?>
            <?php if ($t['is_closed']): ?><span class="badge badge-closed">Geschlossen</span><?php endif; ?>
            <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $t['thread_id'] ?>" class="thread-title-link">
              <?= e($t['title']) ?>
            </a>
          </div>
          <div class="thread-meta">
            von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $t['user_id'] ?>"><?= e($t['author']) ?></a>
            &bull; <?= e(format_date($t['created_at'])) ?>
          </div>
        </td>
        <td class="stat-cell" data-label="Antworten"><?= (int) $t['reply_count'] ?></td>
        <td class="stat-cell" data-label="Aufrufe"><?= number_format((int) $t['view_count'], 0, ',', '.') ?></td>
        <td class="last-post-cell" data-label="Letzter Beitrag">
          <?php if (!empty($t['last_post_date'])): ?>
            <?= e(format_date($t['last_post_date'])) ?><br>
            von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $t['last_poster_id'] ?>">
              <?= e($t['last_poster']) ?>
            </a>
          <?php else: ?>
            <span style="color:var(--text-faint)">–</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $pagination ?>
