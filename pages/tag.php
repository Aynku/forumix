<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// tag.php – Threads nach Tag
require_once __DIR__ . '/../bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$tag  = $slug ? db_fetch('SELECT * FROM tags WHERE slug = ?', [$slug]) : null;

if (!$tag) {
    $tags = all_tags();
    render('tag_index', [
        'title' => 'Alle Tags',
        'tags'  => $tags,
    ]);
    exit;
}

$threads = threads_by_tag((int) $tag['tag_id'], 100);

render('tag', [
    'title'   => 'Tag: ' . $tag['name'],
    'tag'     => $tag,
    'threads' => $threads,
]);
