<?php
require_once __DIR__ . '/auth.php';

$user      = current_user();
$db        = get_db();
$site_name = get_setting('site_name', 'SimpleBlog');

$chunk = 5;
$monthFilter = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : null;

$local_tz = new DateTimeZone(get_setting('timezone', 'UTC'));
$now      = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

if ($monthFilter) {
    $cnt = $db->prepare("SELECT COUNT(*) FROM posts WHERE created_at <= ? AND hidden = 0 AND strftime('%Y-%m', datetime(created_at)) = ?");
    $cnt->execute([$now, $monthFilter]);
    $total = (int)$cnt->fetchColumn();
    $stmt = $db->prepare("SELECT id, title, content, created_at, pinned, slug FROM posts WHERE created_at <= ? AND hidden = 0 AND strftime('%Y-%m', datetime(created_at)) = ? ORDER BY pinned DESC, created_at DESC LIMIT ?");
    $stmt->execute([$now, $monthFilter, $chunk]);
} else {
    $cnt = $db->prepare('SELECT COUNT(*) FROM posts WHERE created_at <= ? AND hidden = 0');
    $cnt->execute([$now]);
    $total = (int)$cnt->fetchColumn();
    $stmt = $db->prepare('SELECT id, title, content, created_at, pinned, slug FROM posts WHERE created_at <= ? AND hidden = 0 ORDER BY pinned DESC, created_at DESC LIMIT ?');
    $stmt->execute([$now, $chunk]);
}
$posts = $stmt->fetchAll();

$post_comments = [];
if (!empty($posts)) {
    $pids = array_column($posts, 'id');
    $ph   = implode(',', array_fill(0, count($pids), '?'));
    $cs   = $db->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON u.id=c.user_id WHERE c.type='post' AND c.content_id IN ($ph) ORDER BY c.created_at ASC");
    $cs->execute($pids);
    foreach ($cs->fetchAll() as $c) $post_comments[$c['content_id']][] = $c;
}
$csrf    = csrf_token();
$isAdmin = $user && $user['role'] === 'admin';

$tlStmt = $db->prepare(
    "SELECT id, title, slug,
            strftime('%Y', datetime(created_at)) AS yr,
            strftime('%m', datetime(created_at)) AS mo
     FROM posts WHERE created_at <= ? AND hidden = 0
     ORDER BY created_at DESC"
);
$tlStmt->execute([$now]);
$archive = [];   // year => month-number => [posts], all newest-first
foreach ($tlStmt->fetchAll() as $r) {
    $archive[$r['yr']][$r['mo']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>

<?php $nav_active = 'home'; $nav_user = $user; require __DIR__ . '/_nav.php'; ?>

<main class="read-wrap">

    <?php if (!empty($archive)): ?>
    <details class="archive">
        <summary>Archive</summary>
        <div class="archive-tree">
            <?php foreach ($archive as $yr => $months): ?>
            <details class="arch-year">
                <summary><?= htmlspecialchars($yr) ?><span class="count">(<?= array_sum(array_map('count', $months)) ?>)</span></summary>
                <?php foreach ($months as $mo => $mposts): ?>
                <details class="arch-month">
                    <summary><?= htmlspecialchars(date('F', mktime(0, 0, 0, (int)$mo, 1))) ?><span class="count">(<?= count($mposts) ?>)</span></summary>
                    <ul class="arch-posts">
                        <?php foreach ($mposts as $p): ?>
                        <li><a href="/post/<?= rawurlencode($p['slug']) ?>" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endforeach; ?>
            </details>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>

    <?php if ($monthFilter): ?>
    <div class="filter-banner">
        <span>Showing posts from <strong><?= htmlspecialchars(date('F Y', mktime(0,0,0,(int)explode('-',$monthFilter)[1],1,(int)explode('-',$monthFilter)[0]))) ?></strong></span>
        <a href="/">← All posts</a>
    </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <div class="glyph">¶</div>
            <p>No posts yet.</p>
            <?php if ($isAdmin): ?>
                <a href="/admin_posts.php" class="btn btn-primary" style="margin-top:1.25rem">Write the first post</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="post-list">
        <?php foreach ($posts as $post):
            $comments  = $post_comments[$post['id']] ?? [];
            $redir     = '/' . ($monthFilter ? '?month=' . urlencode($monthFilter) : '') . '#post-' . (int)$post['id'];
            $permalink = '/post/' . rawurlencode($post['slug'] ?? '');
            require __DIR__ . '/_post_article.php';
        endforeach; ?>
        </div>

        <div id="posts-sentinel" style="height:1px"></div>
        <div id="posts-loading" style="display:none;text-align:center;padding:1.5rem 0;color:var(--text-muted);font-size:.85rem">Loading…</div>
    <?php endif; ?>

</main>

<?php require __DIR__ . '/_footer.php'; ?>

<script nonce="<?= csp_nonce() ?>">
(function () {
    const sentinel = document.getElementById('posts-sentinel');
    const loading  = document.getElementById('posts-loading');
    if (!sentinel) return;

    const CHUNK = <?= (int)$chunk ?>;
    const MONTH_PARAM = <?= json_encode($monthFilter ? '&month=' . $monthFilter : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let offset  = <?= count($posts) ?>;
    let hasMore = <?= json_encode($total > count($posts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let busy    = false;

    if (!hasMore) { sentinel.remove(); return; }

    async function loadMore() {
        if (busy || !hasMore) return;
        busy = true; loading.style.display = '';
        try {
            const res  = await fetch('/posts_chunk.php?offset=' + offset + '&limit=' + CHUNK + MONTH_PARAM);
            const html = await res.text();
            if (!html.trim()) { hasMore = false; sentinel.remove(); return; }
            const tpl = document.createElement('template');
            tpl.innerHTML = html;
            const frag = tpl.content;
            const marker = frag.querySelector('[data-chunk-count]');
            const count = marker ? parseInt(marker.dataset.chunkCount, 10) : 0;
            if (marker) marker.remove();
            sentinel.parentNode.insertBefore(frag, sentinel);
            offset += count;
            if (count < CHUNK) { hasMore = false; sentinel.remove(); }
        } catch (e) {
            console.error('posts_chunk fetch failed', e);
        } finally {
            busy = false; loading.style.display = 'none';
        }
    }

    const obs = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) loadMore();
    }, { root: document.querySelector('.read-wrap'), rootMargin: '400px' });
    obs.observe(sentinel);
})();
</script>

<?php require __DIR__ . '/_comments_script.php'; ?>

</body>
</html>
