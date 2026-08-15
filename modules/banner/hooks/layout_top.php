<?php
// Laeuft auf jeder Seite ganz oben, solange das Modul aktiv ist.
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$cfg  = module_config('banner');
$text = trim((string) ($cfg['text'] ?? ''));
if (empty($cfg['aktiv']) || $text === '') return;

$farben = [
    'info'    => 'var(--color-info, #3b82f6)',
    'warnung' => 'var(--color-warning)',
    'erfolg'  => 'var(--color-success)',
];
$farbe = $farben[$cfg['art'] ?? 'info'] ?? $farben['info'];

// Der Schluessel haengt am Text: aendert die Ankuendigung sich, sehen auch die
// das Band wieder, die das alte weggeklickt hatten.
$schluessel = 'banner-' . substr(md5($text), 0, 8);
?>
<div id="<?= e($schluessel) ?>" class="forumix-banner" hidden
     style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;
            padding:10px 18px;font-size:14px;
            background:color-mix(in srgb, <?= $farbe ?> 16%, transparent);
            border-bottom:2px solid <?= $farbe ?>">
  <span style="flex:1;min-width:200px"><?= nl2br(e($text)) ?></span>
  <button type="button" aria-label="Hinweis schließen"
          style="background:none;border:0;color:inherit;cursor:pointer;font-size:18px;line-height:1">×</button>
</div>
<script>
(function () {
  var el = document.getElementById(<?= json_encode($schluessel) ?>);
  if (!el) return;
  // Erst zeigen, wenn feststeht, dass es nicht weggeklickt wurde — sonst
  // blitzt es bei jedem Seitenaufruf kurz auf.
  try { if (localStorage.getItem(el.id) === '1') return; } catch (e) {}
  el.hidden = false;
  el.querySelector('button').addEventListener('click', function () {
    el.hidden = true;
    try { localStorage.setItem(el.id, '1'); } catch (e) {}
  });
})();
</script>
