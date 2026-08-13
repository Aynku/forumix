<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var string $action; @var bool $isGlobalMod; @var array $modBoards */
  $stats = $stats ?? [];
  $recentActions = $recentActions ?? [];
  $pendingThreads = $pendingThreads ?? [];
  $pendingPosts = $pendingPosts ?? [];
  $reports = $reports ?? [];
  $logs = $logs ?? [];
?>

<div class="section-heading" style="display:flex;justify-content:space-between;align-items:center">
  <span>🛡️ Moderator-Bereich</span>
  <span style="font-size:12px;color:var(--text-muted);font-weight:400">
    <?php if ($isGlobalMod): ?>
      Globaler Moderator
    <?php else: ?>
      Mod in <?= count($modBoards) ?> Forum<?= count($modBoards) === 1 ? '' : 'en' ?>
    <?php endif; ?>
  </span>
</div>

<?php /* ── Mod-Navi ── */ ?>
<div class="mod-nav">
  <a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">📊 Übersicht</a>
  <a href="?action=approval"  class="<?= $action === 'approval' ? 'active' : '' ?>">🚦 Freigabe</a>
  <a href="?action=reports"   class="<?= $action === 'reports' ? 'active' : '' ?>">🚨 Meldungen</a>
  <a href="?action=modlog"    class="<?= $action === 'modlog' ? 'active' : '' ?>">📜 Meine Aktionen</a>
</div>

<?php /* ════════════ DASHBOARD ════════════ */ ?>
<?php if ($action === 'dashboard'): ?>
  <div class="stat-grid" style="margin-bottom:18px">
    <a href="?action=approval" class="stat-box" style="text-decoration:none">
      <div class="stat-icon">📝</div>
      <div class="stat-num"><?= (int) ($stats['pending_threads'] ?? 0) ?></div>
      <div class="stat-label">Neue Themen</div>
    </a>
    <a href="?action=approval" class="stat-box" style="text-decoration:none">
      <div class="stat-icon">💬</div>
      <div class="stat-num"><?= (int) ($stats['pending_posts'] ?? 0) ?></div>
      <div class="stat-label">Neue Antworten</div>
    </a>
    <a href="?action=reports" class="stat-box" style="text-decoration:none">
      <div class="stat-icon">🚨</div>
      <div class="stat-num"><?= (int) ($stats['open_reports'] ?? 0) ?></div>
      <div class="stat-label">Offene Meldungen</div>
    </a>
    <div class="stat-box">
      <div class="stat-icon">🛡️</div>
      <div class="stat-num"><?= $isGlobalMod ? '∞' : (int) ($stats['my_mod_boards'] ?? 0) ?></div>
      <div class="stat-label"><?= $isGlobalMod ? 'Alle Foren' : 'Deine Foren' ?></div>
    </div>
  </div>

  <?php if (!$isGlobalMod && !empty($modBoards)): ?>
    <div class="card" style="padding:18px;margin-bottom:16px">
      <h3 style="font-size:14px;margin-bottom:10px">🛡️ Foren in denen du Moderator bist</h3>
      <?php
        $boardNames = db_fetchAll(
          'SELECT board_id, title FROM boards WHERE board_id IN (' . implode(',', array_fill(0, count($modBoards), '?')) . ')',
          $modBoards
        );
      ?>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($boardNames as $b): ?>
          <a href="<?= e(FORUM_URL) ?>/board.php?id=<?= (int) $b['board_id'] ?>"
             class="saved-search-chip" style="text-decoration:none">
            <span><?= e($b['title']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($recentActions)): ?>
    <div class="card" style="padding:18px">
      <h3 style="font-size:14px;margin-bottom:10px">📜 Deine letzten Aktionen</h3>
      <div class="dash-list">
        <?php foreach ($recentActions as $log): ?>
          <div class="dash-list-item" style="cursor:default">
            <div style="font-size:18px">⚡</div>
            <div class="dash-list-main">
              <div class="dash-list-title"><?= e($log['action'] ?? '–') ?></div>
              <div class="dash-list-snippet">
                <?php if (!empty($log['target_username'])): ?>
                  Ziel: <?= e($log['target_username']) ?> ·
                <?php endif; ?>
                <?= e(format_date($log['created_at'])) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

<?php /* ════════════ FREIGABE ════════════ */ ?>
<?php elseif ($action === 'approval'): ?>

  <?php if (empty($pendingThreads) && empty($pendingPosts)): ?>
    <div class="card" style="padding:40px;text-align:center">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <strong style="font-size:16px">Alles freigegeben!</strong>
      <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
        Keine wartenden Beiträge in deinen Foren.
      </p>
    </div>
  <?php endif; ?>

  <?php if (!empty($pendingThreads)): ?>
    <div class="section-heading">🆕 Neue Themen (<?= count($pendingThreads) ?>)</div>
    <?php foreach ($pendingThreads as $t): ?>
      <div class="card" style="margin-bottom:12px;padding:16px 20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px">
          <div>
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
              In <strong><?= e($t['board_title']) ?></strong> · vor <?= e(format_date($t['created_at'])) ?>
            </div>
            <h3 style="font-size:16px;margin-bottom:6px"><?= e($t['title']) ?></h3>
            <div style="font-size:12px;color:var(--text-muted)">
              Von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $t['user_id'] ?>"><strong><?= e($t['username']) ?></strong></a>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="?action=approve_thread&id=<?= (int) $t['thread_id'] ?>&csrf=<?= e($csrfToken) ?>"
               class="btn btn-primary btn-sm"
               onclick="return confirm('Thema freigeben?')">✅ Freigeben</a>
            <a href="?action=reject_thread&id=<?= (int) $t['thread_id'] ?>&csrf=<?= e($csrfToken) ?>&reason=<?= urlencode('Verstoß gegen Forenregeln') ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Thema ablehnen und löschen?')">🗑️ Ablehnen</a>
          </div>
        </div>
        <?php if (!empty($t['content'])): ?>
          <div style="padding:12px 14px;background:var(--bg-surface2);border-radius:var(--radius-sm);font-size:13px;max-height:160px;overflow-y:auto">
            <?= bbcode(mb_substr($t['content'], 0, 500)) ?>
            <?php if (mb_strlen($t['content']) > 500): ?>…<?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($pendingPosts)): ?>
    <div class="section-heading" style="margin-top:24px">💬 Neue Antworten (<?= count($pendingPosts) ?>)</div>
    <?php foreach ($pendingPosts as $p): ?>
      <div class="card" style="margin-bottom:12px;padding:16px 20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px">
          <div>
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
              In <strong><?= e($p['board_title']) ?></strong> · Thread:
              <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $p['thread_id'] ?>"><?= e($p['thread_title']) ?></a>
              · vor <?= e(format_date($p['created_at'])) ?>
            </div>
            <div style="font-size:12px;color:var(--text-muted)">
              Von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $p['user_id'] ?>"><strong><?= e($p['username']) ?></strong></a>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="?action=approve_post&id=<?= (int) $p['post_id'] ?>&csrf=<?= e($csrfToken) ?>"
               class="btn btn-primary btn-sm"
               onclick="return confirm('Beitrag freigeben?')">✅ Freigeben</a>
            <a href="?action=reject_post&id=<?= (int) $p['post_id'] ?>&csrf=<?= e($csrfToken) ?>&reason=<?= urlencode('Verstoß gegen Forenregeln') ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Beitrag ablehnen und löschen?')">🗑️ Ablehnen</a>
          </div>
        </div>
        <div style="padding:12px 14px;background:var(--bg-surface2);border-radius:var(--radius-sm);font-size:13px;max-height:160px;overflow-y:auto">
          <?= bbcode(mb_substr($p['content'], 0, 500)) ?>
          <?php if (mb_strlen($p['content']) > 500): ?>…<?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php /* ════════════ MELDUNGEN ════════════ */ ?>
<?php elseif ($action === 'reports'): ?>
  <?php if (empty($reports)): ?>
    <div class="card" style="padding:40px;text-align:center">
      <div style="font-size:48px;margin-bottom:12px">😌</div>
      <strong style="font-size:16px">Keine offenen Meldungen</strong>
    </div>
  <?php else: ?>
    <?php foreach ($reports as $r): ?>
      <div class="card" style="margin-bottom:12px;padding:16px 20px">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px">
          <?php if (!empty($r['board_title'])): ?>
            In <strong><?= e($r['board_title']) ?></strong> ·
          <?php endif; ?>
          Gemeldet von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $r['reporter_id'] ?>"><strong><?= e($r['reporter_name']) ?></strong></a>
          · <?= e(format_date($r['created_at'])) ?>
        </div>

        <div style="margin-bottom:8px">
          <strong style="font-size:14px">Grund:</strong>
          <span style="color:var(--text-muted)"><?= e($r['reason'] ?? '–') ?></span>
        </div>

        <?php if (!empty($r['post_content'])): ?>
          <div style="font-size:11px;color:var(--text-faint);margin-bottom:4px">Gemeldeter Beitrag:</div>
          <div style="padding:10px 12px;background:var(--bg-surface2);border-radius:var(--radius-sm);font-size:12px;max-height:120px;overflow-y:auto;margin-bottom:10px">
            <strong><?= e($r['post_author']) ?>:</strong>
            <?= bbcode(mb_substr($r['post_content'], 0, 400)) ?>
          </div>
        <?php endif; ?>

        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
          <?php if (!empty($r['thread_id'])): ?>
            <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $r['thread_id'] ?>#post<?= (int) $r['post_id'] ?>"
               class="btn btn-ghost btn-sm" target="_blank">👁️ Im Thread ansehen</a>
          <?php endif; ?>

          <form method="post" action="?action=report_action" style="display:inline-flex;gap:6px">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="report_id" value="<?= (int) $r['report_id'] ?>">
            <button type="submit" name="status" value="reviewed" class="btn btn-primary btn-sm">✓ Erledigt</button>
            <button type="submit" name="status" value="dismissed" class="btn btn-ghost btn-sm">✗ Verwerfen</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php /* ════════════ MOD-LOG ════════════ */ ?>
<?php elseif ($action === 'modlog'): ?>
  <div class="card" style="padding:18px">
    <h3 style="font-size:14px;margin-bottom:10px">📜 Deine Moderator-Aktionen</h3>
    <?php if (empty($logs)): ?>
      <div style="color:var(--text-faint);font-size:13px;font-style:italic;padding:20px;text-align:center">
        Noch keine Aktionen gespeichert.
      </div>
    <?php else: ?>
      <table class="forum-table">
        <thead>
          <tr>
            <th>Aktion</th>
            <th>Ziel</th>
            <th>Details</th>
            <th>Wann</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><strong><?= e($log['action'] ?? '–') ?></strong></td>
            <td>
              <?php if (!empty($log['target_username'])): ?>
                <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $log['target_user_id'] ?>">
                  <?= e($log['target_username']) ?>
                </a>
              <?php else: ?>
                –
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-muted)"><?= e($log['details'] ?? '') ?></td>
            <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($log['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>
