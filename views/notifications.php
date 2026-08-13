<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $notifications */ ?>

<div class="section-heading">
  🔔 <?= e(t('notifications')) ?>
  <?php if (!empty($notifications)): ?>
    <span style="display:inline-flex;gap:6px">
      <a href="?mark_all=1&csrf=<?= e($csrfToken) ?>" class="btn btn-ghost btn-sm">
        ✓ <?= e(t('mark_all_read')) ?>
      </a>
      <a href="?delete_read=1&csrf=<?= e($csrfToken) ?>"
         class="btn btn-ghost btn-sm"
         onclick="return confirm('Alle bereits gelesenen Benachrichtigungen wirklich löschen?')">
        🧹 Gelesene löschen
      </a>
      <a href="?delete_all=1&csrf=<?= e($csrfToken) ?>"
         class="btn btn-ghost btn-sm action-danger"
         style="color:var(--color-danger);border-color:rgba(220,38,38,.3)"
         onclick="return confirm('WIRKLICH alle Benachrichtigungen löschen? Auch ungelesene!')">
        🗑️ Alle löschen
      </a>
    </span>
  <?php endif; ?>
</div>

<div class="card">
  <?php if (empty($notifications)): ?>
    <div style="padding:30px;text-align:center;color:var(--text-faint)">
      <?= e(t('no_notifications')) ?>
    </div>
  <?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <div class="notif-row pm-list-item <?= !$n['is_read'] ? 'unread' : '' ?>">
      <a href="?read=<?= (int) $n['notif_id'] ?>"
         class="notif-main"
         style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:12px;flex:1;min-width:0">
        <div style="font-size:18px">
          <?php
            echo match ($n['type']) {
                'reply'    => '💬',
                'mention'  => '📌',
                'pm'       => '📨',
                'warning'  => '⚠️',
                'report'   => '🚨',
                'approved' => '✅',
                'rejected' => '🚫',
                'mod_edit' => '✏️',
                default    => '🔔',
            };
          ?>
        </div>
        <div style="flex:1;min-width:0">
          <div class="pm-subject" style="color:var(--text-base)">
            <?php
              $from = e($n['from_username'] ?? 'System');
              $tt   = e($n['thread_title']  ?? '');
              $ps   = e($n['pm_subject']    ?? '');
              echo match ($n['type']) {
                  'reply'    => t('notif_reply',   $from, $tt),
                  'mention'  => t('notif_mention', $from, $tt),
                  'pm'       => t('notif_pm',      $from, $ps),
                  'warning'  => '⚠️ Du hast eine Verwarnung von <strong>' . $from . '</strong> erhalten',
                  'report'   => '🚨 Neue Meldung von <strong>' . $from . '</strong>' . ($tt ? ' in „' . $tt . '"' : ''),
                  'approved' => '✅ Dein Beitrag' . ($tt ? ' in „' . $tt . '"' : '') . ' wurde <strong>freigegeben</strong>',
                  'rejected' => '🚫 Dein Beitrag' . ($tt ? ' in „' . $tt . '"' : '') . ' wurde leider <strong>abgelehnt</strong>',
                  'mod_edit' => '✏️ Dein Beitrag' . ($tt ? ' in „' . $tt . '"' : '') . ' wurde von <strong>' . $from . '</strong> bearbeitet',
                  default    => 'Neue Benachrichtigung',
              };
            ?>
          </div>
          <div class="pm-meta" style="font-size:11px;color:var(--text-faint);margin-top:2px"><?= e(format_date($n['created_at'])) ?></div>
        </div>
      </a>
      <a href="?delete=<?= (int) $n['notif_id'] ?>&csrf=<?= e($csrfToken) ?>"
         class="notif-delete"
         title="Löschen"
         onclick="return confirm('Diese Benachrichtigung löschen?')">
        ✕
      </a>
    </div>
  <?php endforeach; ?>
</div>
