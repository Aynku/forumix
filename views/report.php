<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $post; @var array $reasons; @var string $error */ ?>

<div class="section-heading">Beitrag melden</div>

<div class="form-card">
  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
    Du meldest <strong>Beitrag #<?= (int) $post['post_id'] ?></strong>.
    Die Moderatoren prüfen die Meldung schnellstmöglich.
  </p>

  <div class="card" style="margin-bottom:16px;padding:12px 14px;background:var(--bg-surface2)">
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">Auszug aus dem Beitrag:</div>
    <div style="font-size:13px;font-style:italic;color:var(--text-muted)">
      <?= e(mb_substr(strip_tags($post['content']), 0, 200)) ?><?= mb_strlen($post['content']) > 200 ? '…' : '' ?>
    </div>
  </div>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

    <div class="form-row">
      <label>Grund</label>
      <select name="reason" required>
        <option value="">– Bitte auswählen –</option>
        <?php foreach ($reasons as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label>Anmerkung (optional)</label>
      <textarea name="comment" maxlength="1000" placeholder="Zusätzliche Informationen für die Moderation…" style="min-height:90px"></textarea>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-danger">Meldung absenden</button>
      <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $post['thread_id'] ?>" class="btn btn-ghost">Abbrechen</a>
    </div>
  </form>
</div>
