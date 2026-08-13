<?php
// modules/faq/faq.php – Öffentliche FAQ-Seite
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

if (!module_enabled('faq')) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Die FAQ ist aktuell nicht aktiv.']);
    return;
}

$cu       = current_user();
$canAdmin = $cu && ($cu['is_admin'] || !empty($cu['is_owner']));

// ── Admin-Aktionen ────────────────────────────────────────────────
if ($canAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) die('CSRF');
    $act = $_POST['faq_act'] ?? '';

    if ($act === 'add_item') {
        $catId    = (int) ($_POST['cat_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        if ($catId && $question && $answer) {
            $maxPos = (int) db_count('SELECT COALESCE(MAX(position),0) FROM faq_items WHERE cat_id = ?', [$catId]);
            db_execute('INSERT INTO faq_items (cat_id, question, answer, position) VALUES (?, ?, ?, ?)',
                [$catId, $question, $answer, $maxPos + 10]);
            flash('Frage hinzugefügt.', 'success');
        } else {
            flash('Bitte alle Felder ausfüllen.', 'error');
        }
        redirect(FORUM_URL . '/modules/faq/faq.php?admin=1');
    }

    if ($act === 'edit_item') {
        $id       = (int) ($_POST['faq_id'] ?? 0);
        $catId    = (int) ($_POST['cat_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $visible  = isset($_POST['visible']) ? 1 : 0;
        if ($id && $catId && $question && $answer) {
            db_execute('UPDATE faq_items SET cat_id = ?, question = ?, answer = ?, visible = ? WHERE faq_id = ?',
                [$catId, $question, $answer, $visible, $id]);
            flash('Frage aktualisiert.', 'success');
        }
        redirect(FORUM_URL . '/modules/faq/faq.php?admin=1');
    }

    if ($act === 'delete_item') {
        $id = (int) ($_POST['faq_id'] ?? 0);
        if ($id) {
            db_execute('DELETE FROM faq_items WHERE faq_id = ?', [$id]);
            flash('Frage gelöscht.', 'success');
        }
        redirect(FORUM_URL . '/modules/faq/faq.php?admin=1');
    }

    if ($act === 'move_item') {
        $id  = (int) ($_POST['faq_id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $cur = db_fetch('SELECT cat_id, position FROM faq_items WHERE faq_id = ?', [$id]);
        if ($cur) {
            $op = $dir === 'up' ? '<' : '>';
            $order = $dir === 'up' ? 'DESC' : 'ASC';
            $neighbor = db_fetch("SELECT faq_id, position FROM faq_items
                                  WHERE cat_id = ? AND position $op ?
                                  ORDER BY position $order LIMIT 1",
                [$cur['cat_id'], $cur['position']]);
            if ($neighbor) {
                db_execute('UPDATE faq_items SET position = ? WHERE faq_id = ?', [$neighbor['position'], $id]);
                db_execute('UPDATE faq_items SET position = ? WHERE faq_id = ?', [$cur['position'], $neighbor['faq_id']]);
            }
        }
        redirect(FORUM_URL . '/modules/faq/faq.php?admin=1');
    }

    if ($act === 'add_cat') {
        $title = trim($_POST['title'] ?? '');
        $icon  = trim($_POST['icon'] ?? '📂');
        if ($title) {
            $maxPos = (int) db_count('SELECT COALESCE(MAX(position),0) FROM faq_categories');
            db_execute('INSERT INTO faq_categories (title, icon, position) VALUES (?, ?, ?)',
                [$title, $icon, $maxPos + 10]);
            flash('Kategorie angelegt.', 'success');
        }
        redirect(FORUM_URL . '/modules/faq/faq.php?admin=1');
    }

    if ($act === 'delete_cat') {
        $catId = (int) ($_POST['cat_id'] ?? 0);
        if ($catId) {
            $count = (int) db_count('SELECT COUNT(*) FROM faq_items WHERE cat_id = ?', [$catId]);
            if ($count > 0) {
                flash('Kategorie enthält noch Fragen – diese erst löschen oder verschieben.', 'error');
            } else {
                db_execute('DELETE FROM faq_categories WHERE cat_id = ?', [$catId]);
                flash('Kategorie gelöscht.', 'success');
            }
        }
        redirect(FORUM_URL . '/modules/faq/faq.php?admin=1');
    }
}

// ── Daten laden ───────────────────────────────────────────────────
$adminMode  = $canAdmin && isset($_GET['admin']);
$categories = db_fetchAll('SELECT * FROM faq_categories ORDER BY position, cat_id');
$itemFilter = $adminMode ? '' : ' AND visible = 1';
$items      = db_fetchAll("SELECT * FROM faq_items WHERE 1 = 1 $itemFilter ORDER BY cat_id, position, faq_id");

// Items pro Kategorie gruppieren
$byCategory = [];
foreach ($items as $it) $byCategory[(int) $it['cat_id']][] = $it;

render('@faq/faq', [
    'title'       => '❓ FAQ – Häufige Fragen',
    'categories'  => $categories,
    'byCategory'  => $byCategory,
    'canAdmin'    => $canAdmin,
    'adminMode'   => $adminMode,
]);
