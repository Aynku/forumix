<?php
/**
 * views/partials/editor.php – Wiederverwendbarer WYSIWYG-Editor (BBCode-Output)
 *
 * Erwartet (per extract() oder direkten Variablen):
 *   $editorId           string  - HTML-ID für das Textarea (default: 'content')
 *   $editorName         string  - name-Attribut der Textarea (default: 'content')
 *   $editorValue        string  - Vorbelegter Inhalt (default: '')
 *   $editorPlaceholder  string  - Placeholder-Text (default: '')
 *   $editorRequired     bool    - required-Attribut (default: true)
 *
 * Render: <?php include __DIR__ . '/partials/editor.php'; ?>
 *
 * Oder mit Variablen-Setzen:
 *   <?php $editorId='reply'; include __DIR__ . '/partials/editor.php'; ?>
 */

if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$_eId   = $editorId          ?? 'content';
$_eName = $editorName        ?? 'content';
$_eVal  = $editorValue       ?? '';
$_eHint = $editorPlaceholder ?? t('editor_placeholder');
$_eReq  = ($editorRequired ?? true) ? 'required' : '';
$editorAllowHeadings = $editorAllowHeadings ?? false;
?>
<div class="editor" data-editor-id="<?= e($_eId) ?>">
  <div class="editor-toolbar">
    <div class="editor-toolbar-group">
      <button type="button" class="editor-btn" data-action="bb" data-tag="b" title="Fett (Ctrl+B)"><strong>B</strong></button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="i" title="Kursiv (Ctrl+I)"><em>I</em></button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="u" title="Unterstrichen (Ctrl+U)"><u>U</u></button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="s" title="Durchgestrichen"><s>S</s></button>
    </div>
    <div class="editor-toolbar-sep"></div>
    <?php if (!empty($editorAllowHeadings)): ?>
    <div class="editor-toolbar-group">
      <button type="button" class="editor-btn" data-action="bb" data-tag="h2" title="<?= e(t('editor_h2')) ?>">H2</button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="h3" title="<?= e(t('editor_h3')) ?>">H3</button>
      <button type="button" class="editor-btn" data-action="insert-raw" data-raw="[hr]" title="Horizontale Linie">―</button>
    </div>
    <div class="editor-toolbar-sep"></div>
    <?php endif; ?>
    <div class="editor-toolbar-group">
      <button type="button" class="editor-btn" data-action="bb" data-tag="quote" title="Zitat">❝</button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="code" title="Code">{ }</button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="c" title="<?= e(t('editor_wortwoertlich')) ?>">&lsaquo;&rsaquo;</button>
      <button type="button" class="editor-btn" data-action="bb" data-tag="spoiler" title="Spoiler">👁️</button>
    </div>
    <div class="editor-toolbar-sep"></div>
    <div class="editor-toolbar-group">
      <button type="button" class="editor-btn editor-btn-color" data-action="color" title="Textfarbe">
        <span class="editor-color-swatch">A</span>
      </button>
    </div>
    <div class="editor-toolbar-sep"></div>
    <div class="editor-toolbar-group">
      <button type="button" class="editor-btn" data-action="list" data-list="ul" title="Liste">•</button>
      <button type="button" class="editor-btn" data-action="list" data-list="ol" title="Nummerierte Liste">1.</button>
    </div>
    <div class="editor-toolbar-sep"></div>
    <div class="editor-toolbar-group">
      <button type="button" class="editor-btn" data-action="link" title="<?= e(t('editor_link_einfuegen')) ?>">🔗</button>
      <button type="button" class="editor-btn" data-action="image" title="<?= e(t('editor_bild_einfuegen')) ?>">🖼️</button>
      <button type="button" class="editor-btn" data-action="smileys" title="Smileys">😊</button>
    </div>
    <div class="editor-toolbar-group editor-toolbar-right">
      <?php
        // Platz fuer Module, die eigene Knoepfe mitbringen. Sie bekommen die
        // Kennung des Textfeldes mit — ohne sie wuesste ein Knopf nicht, an
        // welchem der Editoren auf der Seite er haengt.
        if (function_exists('render_module_hook')) {
            render_module_hook('editor_toolbar', ['editorId' => $_eId]);
        }
      ?>
      <button type="button" class="editor-btn editor-btn-preview" data-action="preview" title="<?= e(t('editor_preview')) ?>">👁️ <?= t('preview') ?></button>
    </div>
  </div>

  <textarea
    name="<?= e($_eName) ?>"
    id="<?= e($_eId) ?>"
    class="editor-textarea"
    placeholder="<?= e($_eHint) ?>"
    <?= $_eReq ?>
    rows="8"
  ><?= e($_eVal) ?></textarea>

  <div class="editor-preview" hidden>
    <div class="editor-preview-label"><?= t('editor_preview_label') ?></div>
    <div class="editor-preview-body bbcode-rendered"></div>
  </div>
</div>
