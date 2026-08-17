<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $tags */ ?>

<div class="section-heading">Alle Tags</div>

<div class="card" style="padding:18px">
  <?php if (empty($tags)): ?>
    <p style="color:var(--text-faint);text-align:center">Noch keine Tags vorhanden.</p>
  <?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($tags as $t): ?>
        <a href="<?= e(FORUM_URL) ?>/tag.php?slug=<?= e($t['slug']) ?>"
           class="tag-chip" style="border-color:<?= e($t['color']) ?>;color:<?= e($t['color']) ?>">
          <?= e($t['description'] ? $t['description'] . ' ' : '') ?><?= e($t['name']) ?>
          <span style="opacity:.6">(<?= (int) $t['usage_count'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
