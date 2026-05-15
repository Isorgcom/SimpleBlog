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
$monthFilter = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : null;

if ($monthFilter) {
    $stmt = $db->prepare(
        "SELECT id, title, content, created_at, pinned FROM posts
         WHERE created_at <= ? AND hidden = 0 AND strftime('%Y-%m', datetime(created_at)) = ?
         ORDER BY pinned DESC, created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$now, $monthFilter, $limit, $offset]);
} else {
    $stmt = $db->prepare(
        'SELECT id, title, content, created_at, pinned FROM posts
         WHERE created_at <= ? AND hidden = 0
         ORDER BY pinned DESC, created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$now, $limit, $offset]);
}
$posts = $stmt->fetchAll();

if (empty($posts)) exit;

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
foreach ($posts as $idx => $post):
    $dt = (new DateTime($post['created_at'], new DateTimeZone('UTC')))->setTimezone($local_tz);
    $comments = $post_comments[$post['id']] ?? [];
    $redir = '/' . ($monthFilter ? '?month=' . urlencode($monthFilter) : '') . '#post-' . (int)$post['id'];
    $rt = reading_time($post['content']);
?>
<div class="post-divider"></div>
<article class="post-article" id="post-<?= (int)$post['id'] ?>">
    <div class="post-meta">
        <?php if ($post['pinned']): ?><span class="pin-chip">📌 Pinned</span><?php endif; ?>
        <span><?= htmlspecialchars($dt->format('F j, Y')) ?></span>
        <span class="dot">·</span>
        <span><?= $rt ?> min read</span>
        <?php if ($isAdmin): ?>
        <div class="post-actions">
            <a href="/admin_posts.php?edit=<?= (int)$post['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <form method="post" action="/admin_posts.php" style="margin:0" onsubmit="return confirm('Delete this post?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <h2 class="post-title"><?= htmlspecialchars($post['title']) ?></h2>
    <div class="post-body"><?= sanitize_html($post['content']) ?></div>

    <div class="comments-section" id="csec-<?= (int)$post['id'] ?>">
        <button type="button" class="comments-heading" onclick="toggleComments(<?= (int)$post['id'] ?>)">
            <span class="cmts-chevron">▶</span>
            <?= count($comments) ?> Comment<?= count($comments) !== 1 ? 's' : '' ?>
        </button>
        <div class="comments-body" id="cmts-body-<?= (int)$post['id'] ?>">
            <?php if ($isAdmin && count($comments) > 0): ?>
            <div class="bulk-bar" id="bulk-<?= (int)$post['id'] ?>">
                <span id="bulkcount-<?= (int)$post['id'] ?>">0 selected</span>
                <span class="grow"></span>
                <form method="post" action="/comment.php" style="margin:0;display:contents"
                      onsubmit="return prepareBulkDelete(<?= (int)$post['id'] ?>, this)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="comment_ids" value="">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redir) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete selected</button>
                </form>
                <button type="button" class="btn btn-ghost btn-sm" onclick="clearSel(<?= (int)$post['id'] ?>)">Cancel</button>
            </div>
            <?php endif; ?>

            <?php foreach ($comments as $c): ?>
            <div class="comment" id="cmt-<?= (int)$c['id'] ?>">
                <?php if ($isAdmin): ?>
                <input type="checkbox" class="comment-sel" value="<?= (int)$c['id'] ?>" onchange="onSelChange(<?= (int)$post['id'] ?>)">
                <?php endif; ?>
                <div class="avatar"><?= htmlspecialchars(strtoupper(mb_substr($c['username'], 0, 1))) ?></div>
                <div class="body">
                    <div class="meta">
                        <span class="name"><?= htmlspecialchars($c['username']) ?></span>
                        <span class="when"><?= htmlspecialchars((new DateTime($c['created_at'], new DateTimeZone('UTC')))->setTimezone($local_tz)->format('M j, Y g:i A')) ?></span>
                    </div>
                    <div class="text" id="cbody-<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['body']) ?></div>
                    <?php if ($user && ($user['id'] == $c['user_id'] || $isAdmin)): ?>
                    <div class="actions">
                        <button type="button" onclick="editComment(<?= (int)$c['id'] ?>, this)">Edit</button>
                        <form method="post" action="/comment.php" style="margin:0;display:contents" onsubmit="return confirm('Delete this comment?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redir) ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($user): ?>
            <form method="post" action="/comment.php" class="comment-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" value="post">
                <input type="hidden" name="content_id" value="<?= (int)$post['id'] ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redir) ?>">
                <textarea name="body" placeholder="Write a comment…" required maxlength="2000"></textarea>
                <button type="submit" class="btn btn-primary">Post</button>
            </form>
            <?php else: ?>
            <p style="margin-top:1rem;color:var(--text-muted);font-size:.88rem">
                <a href="/login.php">Sign in</a> to leave a comment.
            </p>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php endforeach; ?>
<div hidden data-chunk-count="<?= $count ?>"></div>
