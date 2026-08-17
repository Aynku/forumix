<?php
// Laeuft je Beitrag in der Themenansicht. $post und $thread kommen aus dem
// Aufruf in pages/thread.php, $currentUser und $csrfToken vom Kern.
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
require_once __DIR__ . '/../lib.php';

$loesung   = loesung_fuer_thread((int) ($thread['thread_id'] ?? 0));
$istLoesung = $loesung && (int) $loesung['post_id'] === (int) $post['post_id'];
$darf       = loesung_darf_markieren($thread ?? null);

// Der erste Beitrag ist die Frage. Die eigene Frage als Loesung auszuzeichnen
// ergibt keinen Sinn.
$istFrage = loesung_erster_beitrag((int) ($thread['thread_id'] ?? 0))
    === (int) ($post['post_id'] ?? 0);

if (!$istLoesung && (!$darf || $istFrage)) return;
?>

<?php if ($istLoesung): ?>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;
              margin:0 0 0;padding:10px 14px;
              background:color-mix(in srgb, var(--color-success) 14%, transparent);
              border-top:2px solid var(--color-success);
              color:var(--color-success);font-size:14px;font-weight:600">
    <span>Dieser Beitrag hat das Problem gelöst</span>
    <?php if ($darf): ?>
      <form method="post" action="<?= e(FORUM_URL) ?>/modules/loesung/markieren.php"
            style="margin-left:auto">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="tun" value="aufheben">
        <input type="hidden" name="thread_id" value="<?= (int) $thread['thread_id'] ?>">
        <input type="hidden" name="post_id" value="<?= (int) $post['post_id'] ?>">
        <button class="btn btn-ghost btn-sm">Markierung aufheben</button>
      </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div style="padding:8px 14px;border-top:1px solid var(--border)">
    <form method="post" action="<?= e(FORUM_URL) ?>/modules/loesung/markieren.php">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="thread_id" value="<?= (int) $thread['thread_id'] ?>">
      <input type="hidden" name="post_id" value="<?= (int) $post['post_id'] ?>">
      <button class="btn btn-ghost btn-sm">Als Lösung markieren</button>
    </form>
  </div>
<?php endif; ?>
