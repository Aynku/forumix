<?php
/**
 * modules/ki/hooks/post_footer.php – Kennzeichnung am Beitrag
 *
 * Sagt, dass an diesem Beitrag ein Sprachmodell mitgeschrieben hat, und was
 * es getan hat. Ohne den Befehl waere die Angabe wertlos: "KI" kann eine
 * korrigierte Kommasetzung heissen oder einen erfundenen Absatz.
 *
 * Aus dem Aufruf in pages/thread.php: $post, $thread.
 */
if (!defined('FORUM_RUNNING')) return;
if (!isset($post['post_id'])) return;

require_once __DIR__ . '/../lib/befehle.php';
require_once __DIR__ . '/../lib/marken.php';

$ki_marken = ki_marken_fuer_thema((int) ($thread['thread_id'] ?? $post['thread_id'] ?? 0));
$ki_befehl = $ki_marken[(int) $post['post_id']] ?? '';
if ($ki_befehl === '') return;

$ki_alle = ki_befehle();
$ki_name = isset($ki_alle[$ki_befehl]['name']) ? t($ki_alle[$ki_befehl]['name']) : $ki_befehl;
?>
<div class="ki-marke" title="<?= e(t('ki_hinweis_titel')) ?>">
  <span aria-hidden="true">✨</span>
  <span><?= e(t('ki_assisted', $ki_name)) ?></span>
</div>
