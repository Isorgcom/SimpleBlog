<?php
require_once __DIR__ . '/auth.php';

$user      = current_user();
$db        = get_db();
$site_name = get_setting('site_name', 'SimpleBlog');
$local_tz  = new DateTimeZone(get_setting('timezone', 'UTC'));
$isAdmin   = $user && $user['role'] === 'admin';
$csrf      = csrf_token();

$slug = trim($_GET['slug'] ?? '');
$now  = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

// Admins may view hidden/scheduled posts; the public only sees published, visible ones.
if ($isAdmin) {
    $stmt = $db->prepare('SELECT id, title, content, created_at, pinned, slug FROM posts WHERE slug = ?');
    $stmt->execute([$slug]);
} else {
    $stmt = $db->prepare('SELECT id, title, content, created_at, pinned, slug FROM posts WHERE slug = ? AND hidden = 0 AND created_at <= ?');
    $stmt->execute([$slug, $now]);
}
$post = ($slug !== '') ? $stmt->fetch() : false;

$comments = [];
if ($post) {
    $post['tags'] = tags_for_posts($db, [$post['id']])[$post['id']] ?? [];
    $cs = $db->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON u.id = c.user_id WHERE c.type = 'post' AND c.content_id = ? ORDER BY c.created_at ASC");
    $cs->execute([$post['id']]);
    $comments  = $cs->fetchAll();
    $permalink = '/post/' . rawurlencode($post['slug']);
    $redir     = $permalink;
} else {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $post ? htmlspecialchars($post['title']) . ' — ' . htmlspecialchars($site_name) : 'Not found — ' . htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>

<?php $nav_active = ''; $nav_user = $user; require __DIR__ . '/_nav.php'; ?>

<main class="read-wrap">
    <div class="filter-banner">
        <a href="/">← All posts</a>
    </div>

    <?php if (!$post): ?>
        <div class="empty-state">
            <div class="glyph">¶</div>
            <p>That post doesn't exist (or isn't published).</p>
            <a href="/" class="btn btn-primary" style="margin-top:1.25rem">Back to all posts</a>
        </div>
    <?php else: ?>
        <div class="post-list">
        <?php require __DIR__ . '/_post_article.php'; ?>
        </div>
    <?php endif; ?>

</main>

<?php require __DIR__ . '/_footer.php'; ?>

<?php require __DIR__ . '/_comments_script.php'; ?>
</body>
</html>
