<?php
/**
 * modules/ki/hooks/editor_toolbar.php – Der Knopf im Editor
 *
 * Wird fuer jeden Editor auf der Seite einmal gezeichnet. Stylesheet und
 * Skript kommen trotzdem nur einmal ins Dokument — auf einer Themenseite mit
 * Schnellantwort und Bearbeiten-Feldern waeren es sonst mehrere.
 *
 * Aus render_module_hook() verfuegbar: $editorId, $currentUser, $csrfToken.
 */

if (!defined('FORUM_RUNNING')) return;

require_once __DIR__ . '/../lib/einstellungen.php';
require_once __DIR__ . '/../lib/befehle.php';

if (!ki_bereit()) return;

$ki_cfg = ki_einstellungen();

// Nur die freigegebenen Befehle, und nur das, was der Browser braucht.
$ki_liste = [];
foreach (ki_befehle() as $code => $b) {
    if (!ki_befehl_frei($code, $ki_cfg)) continue;
    $ki_liste[] = [
        'code'    => $code,
        'name'    => $b['name'],
        'hinweis' => $b['hinweis'],
        'ziel'    => !empty($b['ziel']),
        'marke'   => ki_kennzeichnet($code, $ki_cfg),
    ];
}
if (!$ki_liste) return;

if (empty($GLOBALS['ki_assets_geladen'])) {
    $GLOBALS['ki_assets_geladen'] = true;
    // Das Stylesheet kommt aus hooks/layout_top.php — es wird auch ohne
    // Editor gebraucht, fuer die Kennzeichnung am Beitrag.
    $ki_v = '1.0';
    ?>
    <script>
      window.KI_EINSTELLUNGEN = {
        url:      <?= json_encode(FORUM_URL . '/modules/ki/api.php') ?>,
        csrf:     <?= json_encode($csrfToken ?? '') ?>,
        befehle:  <?= json_encode($ki_liste, JSON_UNESCAPED_UNICODE) ?>,
        sprachen: <?= json_encode(KI_SPRACHEN, JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
    <script src="<?= FORUM_URL ?>/modules/ki/assets/ki.js?v=<?= $ki_v ?>" defer></script>
    <?php
}
?>
<button type="button" class="editor-btn ki-knopf" data-ki-editor="<?= e($editorId ?? 'content') ?>"
        title="Schreibhilfe">✨ KI</button>
