<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $post; @var array $history */ ?>

<div class="section-heading">Edit-Verlauf von Beitrag #<?= (int) $post['post_id'] ?></div>

<p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
  Beitrag von <strong><?= e($post['author_name']) ?></strong> in
  „<a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $post['thread_id'] ?>#post<?= (int) $post['post_id'] ?>"><?= e($post['thread_title']) ?></a>".
</p>

<div class="card" style="margin-bottom:14px">
  <div class="card-header"><span class="card-title">Aktuelle Version</span></div>
  <div style="padding:16px;font-size:13px">
    <?= bbcode($post['content']) ?>
  </div>
  <?php if (!empty($post['edited_at'])): ?>
    <div style="padding:6px 16px;background:var(--bg-surface2);border-top:1px solid var(--border);font-size:11px;color:var(--text-muted)">
      Zuletzt bearbeitet: <?= e(format_date($post['edited_at'])) ?>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($history)): ?>
  <div class="card" style="padding:30px;text-align:center;color:var(--text-faint)">
    <div style="font-size:36px;margin-bottom:8px">📜</div>
    Noch keine Bearbeitungen.
  </div>
<?php else: ?>
  <div class="section-heading">Vorherige Versionen (<?= count($history) ?>)</div>
  <?php foreach ($history as $h): ?>
    <div class="card" style="margin-bottom:10px">
      <div class="card-header">
        <span class="card-title">
          Version vom <?= e(format_date($h['created_at'])) ?>
        </span>
        <span style="font-size:11px;color:var(--text-muted)">
          bearbeitet von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $h['editor_id'] ?>"><?= e($h['editor_name']) ?></a>
        </span>
      </div>
      <?php if (!empty($h['reason'])): ?>
        <div style="padding:8px 16px;background:var(--bg-surface2);border-bottom:1px solid var(--border);font-size:12px;color:var(--text-muted);font-style:italic">
          <?= e($h['reason']) ?>
        </div>
      <?php endif; ?>
      <div style="padding:14px 16px;font-size:13px;background:var(--bg-surface2);border-left:3px solid var(--border-strong);font-family:var(--font-mono);white-space:pre-wrap;color:var(--text-muted)"><?= e($h['old_content']) ?></div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
