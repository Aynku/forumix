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
require_once __DIR__ . '/../lib/marken.php';

// Laeuft die Vorsortierung, muss das am Editor stehen — dort, wo jemand
// gerade schreibt, und nicht nur in der Datenschutzerklaerung. Der Hinweis
// haengt an der Einstellung: ist sie aus, geht auch nichts hinaus, und dann
// darf da auch nichts stehen.
$ki_cfg_mod = ki_einstellungen();
if (!empty($ki_cfg_mod['mod_an']) && (string) ($ki_cfg_mod['schluessel'] ?? '') !== '') {
    echo '<span class="ki-mod-hinweis" title="' . htmlspecialchars(t('ki_autocheck_hint'), ENT_QUOTES, 'UTF-8') . '">'
       . htmlspecialchars(t('ki_autocheck'), ENT_QUOTES, 'UTF-8') . '</span>';
}

if (!ki_bereit()) return;

$ki_cfg = ki_einstellungen();

// Nur die freigegebenen Befehle, und nur das, was der Browser braucht.
$ki_liste = [];
foreach (ki_befehle() as $code => $b) {
    if (!ki_befehl_frei($code, $ki_cfg)) continue;
    $ki_liste[] = [
        'code'    => $code,
        'name'    => t($b['name']),
        'hinweis' => t($b['hinweis']),
        'ziel'    => !empty($b['ziel']),
        'marke'   => ki_kennzeichnet($code, $ki_cfg),
    ];
}
if (!$ki_liste) return;

if (empty($GLOBALS['ki_assets_geladen'])) {
    $GLOBALS['ki_assets_geladen'] = true;
    // Das Stylesheet kommt aus hooks/layout_top.php — es wird auch ohne
    // Editor gebraucht, fuer die Kennzeichnung am Beitrag.
    $ki_v = ki_stempel('assets/ki.js');
    ?>
    <script>
      window.KI_EINSTELLUNGEN = {
        url:      <?= json_encode(FORUM_URL . '/modules/ki/api.php') ?>,
        csrf:     <?= json_encode($csrfToken ?? '') ?>,
        befehle:  <?= json_encode($ki_liste, JSON_UNESCAPED_UNICODE) ?>,
        sprachen: <?= json_encode(ki_sprachen_anzeige(), JSON_UNESCAPED_UNICODE) ?>,
        <?php /* Was ki.js im Browser anzeigt. Eine feste Liste, wie js_texte()
                im Kern — das Modul hat keinen Zugriff auf t() im Browser. */ ?>
        texte:    <?= json_encode([
                      'kennzeichnung_fuss' => t('ki_js_kennzeichnung_fuss'),
                      'kennzeichnung'      => t('ki_js_kennzeichnung'),
                      'wohin_uebersetzen'  => t('ki_js_wohin_uebersetzen'),
                      'schliessen'         => t('ki_js_schliessen'),
                      'zu_lang_zum_faerben'=> t('ki_js_zu_lang_zum_faerben'),
                  ], JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
    <script src="<?= FORUM_URL ?>/modules/ki/assets/ki.js?v=<?= $ki_v ?>" defer></script>
    <?php
}
?>
<button type="button" class="editor-btn ki-knopf" data-ki-editor="<?= e($editorId ?? 'content') ?>"
        title="Schreibhilfe">✨ KI</button>
