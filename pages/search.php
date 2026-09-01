<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// search.php – Erweiterte Suche mit gespeicherten Suchen
require_once __DIR__ . '/../bootstrap.php';

define('SEARCH_PER_PAGE', 20);

$cu = current_user();

// ── GESPEICHERTE SUCHEN: Aktionen ────────────────────────────────────
$savedAction = $_GET['saved'] ?? '';

if ($savedAction === 'save' && $cu) {
    if (!verify_csrf($_POST['csrf'] ?? $_GET['csrf'] ?? '')) error_page(400, t('error_csrf_title'), t('error_csrf_text'));
    $name = trim($_POST['save_name'] ?? '');
    if ($name === '') {
        flash(t('search_name_required'), 'error');
    } else {
        $params = [
            'q'           => trim($_POST['q'] ?? ''),
            'board'       => (int) ($_POST['board'] ?? 0),
            'author_name' => trim($_POST['author_name'] ?? ''),
            'date_from'   => trim($_POST['date_from'] ?? ''),
            'date_to'     => trim($_POST['date_to'] ?? ''),
            'sort'        => $_POST['sort'] ?? 'date_desc',
            'has_reactions'   => isset($_POST['has_reactions']) ? 1 : 0,
            'has_attachments' => isset($_POST['has_attachments']) ? 1 : 0,
        ];
        try {
            db_execute(
                'INSERT INTO saved_searches (user_id, name, params) VALUES (?, ?, ?)',
                [$cu['user_id'], mb_substr($name, 0, 100), json_encode($params)]
            );
            flash(t('search_saved'), 'success');
        } catch (Exception $e) {
            flash(t('search_save_failed', $e->getMessage()), 'error');
        }
    }
    redirect(FORUM_URL . '/search.php?' . http_build_query($_POST));
}

if ($savedAction === 'delete' && $cu) {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('error_csrf_title'), t('error_csrf_text'));
    db_execute('DELETE FROM saved_searches WHERE search_id = ? AND user_id = ?',
        [get_int('id'), $cu['user_id']]);
    flash(t('search_deleted'), 'success');
    redirect(FORUM_URL . '/search.php');
}

if ($savedAction === 'load' && $cu) {
    $row = db_fetch('SELECT params FROM saved_searches WHERE search_id = ? AND user_id = ?',
        [get_int('id'), $cu['user_id']]);
    if ($row) {
        $p = json_decode($row['params'], true) ?: [];
        $p = array_filter($p, fn($v) => $v !== '' && $v !== 0 && $v !== null);
        redirect(FORUM_URL . '/search.php?' . http_build_query($p));
    }
}

// ── EIGENTLICHE SUCHE ───────────────────────────────────────────────
$query    = trim($_GET['q'] ?? '');
$boardId  = get_int('board');
$authorId = get_int('author');
$author   = trim($_GET['author_name'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
// Ohne ausdrueckliche Wahl: mit Suchbegriff nach Treffergüte, sonst nach
// Datum. Wer "Neueste zuerst" auswaehlt, bekommt auch Neueste zuerst.
$sortBy   = $_GET['sort'] ?? (trim((string) ($_GET['q'] ?? '')) !== '' ? 'relevanz' : 'date_desc');
$hasReactions   = !empty($_GET['has_reactions']);
$hasAttachments = !empty($_GET['has_attachments']);
$page     = max(1, get_int('page', 1));
$results  = [];
$total    = 0;
$paginationHtml = '';

if ($author && !$authorId) {
    $au = db_fetch('SELECT user_id FROM users WHERE username = ?', [$author]);
    if ($au) $authorId = (int) $au['user_id'];
}

$hasQuery = mb_strlen($query) >= 3 || $authorId > 0 || $boardId > 0 || $dateFrom || $dateTo || $hasReactions || $hasAttachments;

if ($hasQuery) {
    $where  = [];
    $params = [];

    // Volltext, wenn der Index steht und der Begriff dazu taugt — sonst wie
    // bisher mit LIKE. Was gilt, entscheidet lib/suche.php.
    $volltext = false;
    if (mb_strlen($query) >= 3) {
        $bed      = such_bedingung($query);
        $where[]  = $bed['sql'];
        $params   = array_merge($params, $bed['params']);
        $volltext = $bed['volltext'];
    }
    if ($boardId > 0)  { $where[] = 't.board_id = ?'; $params[] = $boardId; }
    if ($authorId > 0) { $where[] = 'p.user_id = ?'; $params[] = $authorId; }
    if ($dateFrom)     { $where[] = 'p.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo)       { $where[] = 'p.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    if ($hasReactions) {
        $where[] = 'EXISTS (SELECT 1 FROM reactions WHERE post_id = p.post_id)';
    }
    if ($hasAttachments) {
        $where[] = 'EXISTS (SELECT 1 FROM attachments WHERE post_id = p.post_id)';
    }

    $where[] = 'COALESCE(p.is_approved, 1) = 1';
    $where[] = 'COALESCE(t.is_approved, 1) = 1';
    // Was im Papierkorb liegt, ist auch ueber die Suche nicht zu finden —
    // sonst stuende der Text im Auszug, den gerade jemand geloescht hat.
    // Ueber den Helfer, damit auch hier der Schalter greift, wenn die
    // Migration noch aussteht. Er liefert " AND x.deleted_at IS NULL" — das
    // fuehrende AND faellt hier weg, weil die Teile selbst verknuepft werden.
    foreach ([sichtbar_sql('p'), sichtbar_sql('t')] as $teil) {
        if ($teil !== '') $where[] = ltrim(substr($teil, 4));
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    // Passwort-geschützte Foren ausfiltern (Threads müssen über `t.board_id` filtern)
    $boardFilter = board_filter_sql('t', 'board_id');
    if ($boardFilter !== '') {
        $whereSql .= $boardFilter;
    }

    // Nach Treffergüte sortieren geht nur, wenn auch danach gesucht wurde.
    // Steht der Index nicht bereit oder war der Begriff zu kurz, faellt die
    // Auswahl still auf "Neueste zuerst" zurueck — eine Sortierung, die keine
    // Zahlen hat, waere eine leere Behauptung.
    $order = match ($sortBy) {
        'relevanz'  => $volltext ? 'relevanz DESC, p.created_at DESC' : 'p.created_at DESC',
        'date_asc'  => 'p.created_at ASC',
        'replies'   => 't.reply_count DESC',
        'views'     => 't.view_count DESC',
        'reactions' => 'reaction_count DESC, p.created_at DESC',
        default     => 'p.created_at DESC',
    };

    $total = db_count("
        SELECT COUNT(*) FROM (
            SELECT t.thread_id
            FROM threads t JOIN posts p ON p.thread_id = t.thread_id
            $whereSql
            GROUP BY t.thread_id
        ) sub
    ", $params);

    $pag = paginate($total, SEARCH_PER_PAGE, $page);

    // Die Guete steht nur in der Ergebnisabfrage, nicht in der Zaehlung — dort
    // waere sie Arbeit ohne Zweck. Ihre Parameter gehoeren deshalb VOR die
    // uebrigen: sie stehen in der Abfrage weiter vorn.
    $relevanzSpalte = '';
    $sp = array_merge($params, [$pag['per_page'], $pag['offset']]);
    if ($volltext) {
        $rel = such_relevanz($query);
        $relevanzSpalte = ', ' . $rel['sql'] . ' AS relevanz';
        $sp = array_merge($rel['params'], $sp);
    }

    $raw = db_fetchAll("
        SELECT t.thread_id, t.title AS thread_title, t.reply_count, t.view_count,
               b.board_id, b.title AS board_title,
               c.title AS cat_title,
               u.user_id AS author_id, u.username AS author,
               p.post_id, p.content AS post_content, p.created_at AS post_date,
               (SELECT COUNT(*) FROM reactions   WHERE post_id = p.post_id) AS reaction_count,
               (SELECT COUNT(*) FROM attachments WHERE post_id = p.post_id) AS attachment_count
               $relevanzSpalte
        FROM threads t
        JOIN boards b     ON b.board_id = t.board_id
        JOIN categories c ON c.cat_id   = b.cat_id
        JOIN users u      ON u.user_id  = t.user_id
        JOIN posts p      ON p.thread_id = t.thread_id
        $whereSql
        GROUP BY t.thread_id
        ORDER BY $order
        LIMIT ? OFFSET ?
    ", $sp);

    foreach ($raw as $r) {
        if (mb_strlen($query) >= 3) {
            $text  = strip_tags($r['post_content']);
            $pos   = mb_stripos($text, $query);
            $start = max(0, ($pos !== false ? $pos : 0) - 60);
            $snip  = mb_substr($text, $start, 200);
            if ($start > 0) $snip = '…' . $snip;
            $snip = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<mark>$1</mark>', e($snip));
        } else {
            $snip = e(mb_substr(strip_tags($r['post_content']), 0, 200));
        }
        $r['snippet'] = $snip;
        $results[] = $r;
    }

    $url = FORUM_URL . '/search.php?' . http_build_query(array_filter([
        'q'           => $query,
        'board'       => $boardId,
        'author_name' => $author,
        'date_from'   => $dateFrom,
        'date_to'     => $dateTo,
        'sort'        => $sortBy,
        'has_reactions'   => $hasReactions ? 1 : null,
        'has_attachments' => $hasAttachments ? 1 : null,
    ]));
    $paginationHtml = pagination_html($pag, $url);
}

$boards = db_fetchAll('
    SELECT b.board_id, b.title, b.password_hash, c.title AS cat
    FROM boards b JOIN categories c ON c.cat_id = b.cat_id
    ORDER BY c.position, b.position
');
// Passwort-geschützte Boards rausfiltern für nicht-berechtigte User
$boards = array_values(array_filter($boards, fn($b) => board_user_can_access($b)));

$savedSearches = [];
if ($cu) {
    try {
        $savedSearches = db_fetchAll(
            'SELECT search_id, name, params, created_at FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC',
            [$cu['user_id']]
        );
    } catch (Exception $e) {}
}

render('search', [
    'title'          => t('search'),
    'query'          => $query,
    'boardId'        => $boardId,
    'author'         => $author,
    'dateFrom'       => $dateFrom,
    'dateTo'         => $dateTo,
    'sortBy'         => $sortBy,
    'hasReactions'   => $hasReactions,
    'hasAttachments' => $hasAttachments,
    'results'        => $results,
    'total'          => $total,
    'boards'         => $boards,
    'paginationHtml' => $paginationHtml,
    'hasQuery'       => $hasQuery,
    'savedSearches'  => $savedSearches,
]);
