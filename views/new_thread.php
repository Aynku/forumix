<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $board; @var string $error */ ?>
<div class="section-heading">+ Neues Thema in „<?= e($board['title']) ?>"</div>
<div class="form-card">
    <?php if (!empty($error)): ?><div class="flash flash-error">❌ <?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <div class="form-row">
        <label>Titel</label>
        <input type="text" name="title" maxlength="200" value="<?= e($formTitle ?? '') ?>" required autofocus>
      </div>
      <div class="form-row">
        <label>Beitrag</label>
        <?php
          $editorValue = $formContent ?? '';
          $editorPlaceholder = 'Schreibe etwas zum Thema… (BBCode unterstützt)';
          include __DIR__ . '/partials/editor.php';
        ?>
      </div>
      <div class="form-row">
        <label>📎 <?= e(t('attach_file')) ?> (optional, max. 5 MB pro Datei)</label>
        <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,text/plain,.zip,.rar">
      </div>

      <?php if (!empty($availableTags)): ?>
      <div class="form-row">
        <label>🏷️ Tags (max. 5)</label>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($availableTags as $tg): ?>
            <label class="tag-checkbox" style="border-color:<?= e($tg['color']) ?>">
              <input type="checkbox" name="tags[]" value="<?= (int) $tg['tag_id'] ?>" style="margin:0">
              <span style="color:<?= e($tg['color']) ?>"><?= e($tg['description'] ?: '') ?> <?= e($tg['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <details style="margin:14px 0;padding:12px;background:var(--bg-surface2);border:1px solid var(--border);border-radius:6px">
        <summary style="cursor:pointer;font-weight:600">📊 <?= e(t('add_poll')) ?></summary>
        <div style="margin-top:12px">
          <div class="form-row">
            <label><?= e(t('poll_question')) ?></label>
            <input type="text" name="poll_question" maxlength="255" placeholder="z.B. Welches Feature wünscht ihr euch?">
          </div>
          <div class="form-row">
            <label>Optionen (mindestens 2)</label>
            <input type="text" name="poll_options[]" placeholder="Option 1" maxlength="200" style="margin-bottom:6px">
            <input type="text" name="poll_options[]" placeholder="Option 2" maxlength="200" style="margin-bottom:6px">
            <input type="text" name="poll_options[]" placeholder="Option 3 (optional)" maxlength="200" style="margin-bottom:6px">
            <input type="text" name="poll_options[]" placeholder="Option 4 (optional)" maxlength="200">
          </div>
          <div class="form-row">
            <label><input type="checkbox" name="poll_multi" value="1"> Mehrfachauswahl erlauben</label>
          </div>
        </div>
      </details>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Thema erstellen</button>
        <a href="<?= e(FORUM_URL) ?>/board.php?id=<?= (int) $board['board_id'] ?>" class="btn btn-ghost">Abbrechen</a>
      </div>
    </form>
</div>
