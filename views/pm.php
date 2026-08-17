<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $pmView; @var ?array $pm; @var array $messages; @var string $error */ ?>

<div class="section-heading">
  <?php
    if ($pmView === 'new')         echo 'Neue Nachricht';
    elseif ($pmView === 'read')    echo 'Nachricht lesen';
    elseif ($pmView === 'sent')    echo 'Gesendet';
    else                            echo 'Posteingang';
  ?>
  <div style="display:flex;gap:8px">
    <a href="<?= e(FORUM_URL) ?>/pm.php" class="btn btn-ghost btn-sm">Eingang</a>
    <a href="<?= e(FORUM_URL) ?>/pm.php?action=sent" class="btn btn-ghost btn-sm">Gesendet</a>
    <a href="<?= e(FORUM_URL) ?>/pm.php?new=1" class="btn btn-primary btn-sm">+ Neue PN</a>
  </div>
</div>

<?php if ($pmView === 'new'): ?>
  <div class="form-card">
    <?php if (!empty($error)): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <div class="form-row">
        <label>An (Benutzername)</label>
        <input type="text" name="receiver" value="<?= e($formReceiver ?? '') ?>" required autofocus>
      </div>
      <div class="form-row">
        <label>Betreff</label>
        <input type="text" name="subject" value="<?= e($formSubject ?? '') ?>" maxlength="200" required>
      </div>
      <div class="form-row">
        <label>Nachricht</label>
        <?php
          $editorValue = $formContent ?? '';
          $editorPlaceholder = 'Deine private Nachricht…';
          include __DIR__ . '/partials/editor.php';
        ?>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Senden</button>
        <a href="<?= e(FORUM_URL) ?>/pm.php" class="btn btn-ghost">Abbrechen</a>
      </div>
    </form>
  </div>

<?php elseif ($pmView === 'read'):
  $cu = current_user();
  $isSenderView = $cu && (int) $pm['sender_id'] === (int) $cu['user_id'];
?>
  <div class="form-card">
    <h2><?= e($pm['subject']) ?></h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
      <?php if ($isSenderView): ?>
        An <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $pm['receiver_id'] ?>"><?= e($pm['receiver_name']) ?></a>
      <?php else: ?>
        Von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $pm['sender_id'] ?>"><?= e($pm['sender_name']) ?></a>
      <?php endif; ?>
      &bull; <?= e(format_date($pm['sent_at'])) ?>
      <?php if ($isSenderView): ?>
        &bull; <span style="font-style:italic"><?= $pm['is_read'] ? 'Gelesen' : 'Noch nicht gelesen' ?></span>
      <?php endif; ?>
    </p>
    <hr style="border:none;border-top:1px solid var(--border);margin-bottom:14px">
    <div style="font-size:13px;line-height:1.75"><?= bbcode($pm['content']) ?></div>
    <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
    <div style="display:flex;gap:8px">
      <?php if (!$isSenderView): ?>
        <a href="<?= e(FORUM_URL) ?>/pm.php?new=1&to=<?= (int) $pm['sender_id'] ?>&subject=<?= urlencode('Re: ' . $pm['subject']) ?>"
           class="btn btn-primary btn-sm">Antworten</a>
      <?php endif; ?>
      <a href="<?= e(FORUM_URL) ?>/pm.php?action=delete&id=<?= (int) $pm['pm_id'] ?>&csrf=<?= e($csrfToken) ?><?= $isSenderView ? '&from=sent' : '' ?>"
         class="btn btn-danger btn-sm" onclick="return confirm('Löschen?')">Löschen</a>
      <a href="<?= e(FORUM_URL) ?>/pm.php<?= $isSenderView ? '?action=sent' : '' ?>" class="btn btn-ghost btn-sm">← Zurück</a>
    </div>
  </div>

<?php else: ?>
  <div class="card">
    <?php if (empty($messages)): ?>
      <div style="text-align:center;padding:30px;color:var(--text-faint)">Keine Nachrichten.</div>
    <?php endif; ?>
    <?php foreach ($messages as $msg): ?>
      <div class="pm-list-item <?= (!$msg['is_read'] && $pmView !== 'sent') ? 'unread' : '' ?>">
        <div style="flex:1;min-width:0">
          <a href="<?= e(FORUM_URL) ?>/pm.php?action=read&id=<?= (int) $msg['pm_id'] ?>" class="pm-subject">
            <?php if (!$msg['is_read'] && $pmView !== 'sent'): ?><?php endif; ?><?= e($msg['subject']) ?>
          </a>
          <div class="thread-meta">
            <?= $pmView === 'sent' ? 'An' : 'Von' ?>:
            <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $msg['other_id'] ?>"><?= e($msg['other_name']) ?></a>
          </div>
        </div>
        <div class="pm-meta"><?= e(format_date($msg['sent_at'])) ?></div>
        <a href="<?= e(FORUM_URL) ?>/pm.php?action=delete&id=<?= (int) $msg['pm_id'] ?>&csrf=<?= e($csrfToken) ?>&from=<?= $pmView === 'sent' ? 'sent' : 'inbox' ?>"
           class="notif-delete"
           title="Löschen"
           onclick="return confirm('Diese Nachricht löschen?')">
          ✕
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
