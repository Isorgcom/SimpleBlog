<?php
/**
 * Infinite-scroll chunk endpoint.
 * Returns an HTML fragment: <article>s + a trailing marker div.
 * Empty response = no more posts.
 */
require_once __DIR__ . '/auth.php';

$user     = current_user();
$db       = get_db();
$local_tz = new DateTimeZone(get_setting('timezone', 'UTC'));
$now      = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$isAdmin  = $user && $user['role'] === 'admin';
$csrf     = $user ? csrf_token() : '';

$limit       = min(10, max(1, (int)($_GET['limit']  ?? 5)));
$offset      = max(0,         (int)($_GET['offset'] ?? 0));
$tagFilter   = normalize_tag($_GET['tag'] ?? '');
$tagFilter   = $tagFilter !== '' ? $tagFilter : null;
$monthFilter = !$tagFilter && preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : null;

if ($tagFilter) {
    $stmt = $db->prepare(
        "SELECT p.id, p.title, p.content, p.created_at, p.pinned, p.slug FROM posts p
         JOIN post_tags pt ON pt.post_id = p.id
         JOIN tags t ON t.id = pt.tag_id
         WHERE p.created_at <= ? AND p.hidden = 0 AND t.name = ?
         ORDER BY p.pinned DESC, p.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$now, $tagFilter, $limit, $offset]);
} elseif ($monthFilter) {
    $stmt = $db->prepare(
        "SELECT id, title, content, created_at, pinned, slug FROM posts
         WHERE created_at <= ? AND hidden = 0 AND strftime('%Y-%m', datetime(created_at)) = ?
         ORDER BY pinned DESC, created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$now, $monthFilter, $limit, $offset]);
} else {
    $stmt = $db->prepare(
        'SELECT id, title, content, created_at, pinned, slug FROM posts
         WHERE created_at <= ? AND hidden = 0
         ORDER BY pinned DESC, created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$now, $limit, $offset]);
}
$posts = $stmt->fetchAll();

if (empty($posts)) exit;

$tag_map = tags_for_posts($db, array_column($posts, 'id'));
foreach ($posts as &$p) { $p['tags'] = $tag_map[$p['id']] ?? []; }
unset($p);

$pids = array_column($posts, 'id');
$ph   = implode(',', array_fill(0, count($pids), '?'));
$cs   = $db->prepare(
    "SELECT c.*, u.username FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.type = 'post' AND c.content_id IN ($ph)
     ORDER BY c.created_at ASC"
);
$cs->execute($pids);
$post_comments = [];
foreach ($cs->fetchAll() as $c) $post_comments[$c['content_id']][] = $c;

$count = count($posts);
foreach ($posts as $post):
    $comments  = $post_comments[$post['id']] ?? [];
    $redir     = ($tagFilter ? '/tag/' . rawurlencode($tagFilter) : '/' . ($monthFilter ? '?month=' . urlencode($monthFilter) : '')) . '#post-' . (int)$post['id'];
    $permalink = '/post/' . rawurlencode($post['slug'] ?? '');
    require __DIR__ . '/_post_article.php';
endforeach;
?>
<div hidden data-chunk-count="<?= $count ?>"></div>
