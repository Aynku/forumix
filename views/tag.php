<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $tag; @var array $threads */ ?>

<div class="section-heading">
  🏷️ Tag: <span style="color:<?= e($tag['color']) ?>"><?= e($tag['name']) ?></span>
  <span style="font-size:12px;color:var(--text-muted);font-weight:400;text-transform:none">
    <?= count($threads) ?> Threads
  </span>
</div>

<?php if (!empty($tag['description'])): ?>
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px"><?= e($tag['description']) ?></p>
<?php endif; ?>

<div class="card">
  <?php if (empty($threads)): ?>
    <div style="padding:30px;text-align:center;color:var(--text-faint)">
      Noch keine Threads mit diesem Tag.
    </div>
  <?php else: ?>
    <table class="forum-table">
      <thead>
        <tr><th>Thread</th><th>Autor</th><th>Antworten</th><th>Aufrufe</th></tr>
      </thead>
      <tbody>
        <?php foreach ($threads as $t): ?>
          <tr>
            <td>
              <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $t['thread_id'] ?>" class="thread-title-link"><?= e($t['title']) ?></a>
              <div class="thread-meta">
                in <a href="<?= e(FORUM_URL) ?>/board.php?id=<?= (int) $t['board_id'] ?>"><?= e($t['board_title']) ?></a>
              </div>
            </td>
            <td><?= e($t['author']) ?></td>
            <td class="stat-cell"><?= (int) $t['reply_count'] ?></td>
            <td class="stat-cell"><?= (int) $t['view_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<p style="margin-top:14px;text-align:center">
  <a href="<?= e(FORUM_URL) ?>/tag.php" class="btn btn-ghost btn-sm">← Alle Tags</a>
</p>
