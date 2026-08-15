<?php
// Laeuft einmal oberhalb der Beitraege. $thread kommt aus dem Aufruf in
// pages/thread.php.
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
require_once __DIR__ . '/../lib.php';

$loesung = loesung_fuer_thread((int) ($thread['thread_id'] ?? 0));
if (!$loesung) return;

// Wer die Loesung geschrieben hat, steht nicht in der Markierung — dafuer
// einmal nachsehen. Faellt die Abfrage aus, bleibt der Kasten trotzdem
// nuetzlich, nur ohne Namen.
$autor = null;
try {
    $autor = db_fetch(
        'SELECT u.username FROM posts p JOIN users u ON u.user_id = p.user_id
         WHERE p.post_id = ?', [(int) $loesung['post_id']]);
} catch (Exception $e) {}
?>
<div class="card" style="margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;
            border-left:3px solid var(--color-success)">
  <span style="font-size:22px">✅</span>
  <div style="flex:1;min-width:200px">
    <strong style="color:var(--color-success)">Dieses Thema ist gelöst</strong>
    <div style="font-size:13px;color:var(--text-muted);margin-top:2px">
      <?php if ($autor): ?>
        Die Lösung kam von <?= e($autor['username']) ?>.
      <?php else: ?>
        Der markierte Beitrag steht weiter unten.
      <?php endif; ?>
    </div>
  </div>
  <a class="btn btn-ghost btn-sm" href="#post<?= (int) $loesung['post_id'] ?>">Zur Lösung springen</a>
</div>
