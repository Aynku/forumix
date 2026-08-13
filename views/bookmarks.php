<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $bookmarks */ ?>

<div class="section-heading">🔖 Meine Lesezeichen (<?= count($bookmarks) ?>)</div>

<div class="card">
  <?php if (empty($bookmarks)): ?>
    <div style="padding:40px;text-align:center;color:var(--text-faint)">
      <div style="font-size:48px;margin-bottom:8px">🔖</div>
      <p>Noch keine Lesezeichen.</p>
      <p style="font-size:12px;margin-top:8px">Klicke in einem Thread auf 🔖, um ihn hier zu speichern.</p>
    </div>
  <?php else: ?>
    <table class="forum-table">
      <thead>
        <tr><th>Thread</th><th>Autor</th><th>Antworten</th><th>Gespeichert</th></tr>
      </thead>
      <tbody>
        <?php foreach ($bookmarks as $b): ?>
          <tr>
            <td>
              <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $b['thread_id'] ?>"
                 class="thread-title-link">
                <?= e($b['title']) ?>
              </a>
              <div class="thread-meta">
                in <a href="<?= e(FORUM_URL) ?>/board.php?id=<?= (int) $b['board_id'] ?>"><?= e($b['board_title']) ?></a>
              </div>
            </td>
            <td>
              <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $b['author_id'] ?>"><?= e($b['author']) ?></a>
            </td>
            <td class="stat-cell"><?= (int) $b['reply_count'] ?></td>
            <td class="last-post-cell"><?= e(format_date($b['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
