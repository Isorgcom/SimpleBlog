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
    $stmt = $db->prepare("SELECT id, title, content, created_at, pinned FROM posts WHERE created_at <= ? AND hidden = 0 AND strftime('%Y-%m', datetime(created_at)) = ? ORDER BY pinned DESC, created_at DESC LIMIT ?");
    $stmt->execute([$now, $monthFilter, $chunk]);
} else {
    $cnt = $db->prepare('SELECT COUNT(*) FROM posts WHERE created_at <= ? AND hidden = 0');
    $cnt->execute([$now]);
    $total = (int)$cnt->fetchColumn();
    $stmt = $db->prepare('SELECT id, title, content, created_at, pinned FROM posts WHERE created_at <= ? AND hidden = 0 ORDER BY pinned DESC, created_at DESC LIMIT ?');
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
    "SELECT strftime('%Y-%m', datetime(created_at)) AS ym, COUNT(*) AS cnt
     FROM posts WHERE created_at <= ? AND hidden = 0
     GROUP BY ym ORDER BY ym DESC"
);
$tlStmt->execute([$now]);
$tlMonths = $tlStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css">
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>

<?php $nav_active = 'home'; $nav_user = $user; require __DIR__ . '/_nav.php'; ?>

<main class="read-wrap">

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
        <?php $lastIdx = count($posts) - 1; foreach ($posts as $idx => $post):
            $dt = (new DateTime($post['created_at'], new DateTimeZone('UTC')))->setTimezone($local_tz);
            $redir = '/' . ($monthFilter ? '?month=' . urlencode($monthFilter) : '') . '#post-' . (int)$post['id'];
            $comments = $post_comments[$post['id']] ?? [];
            $rt = reading_time($post['content']);
        ?>
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
        <?php if ($idx < $lastIdx): ?><div class="post-divider"></div><?php endif; ?>
        <?php endforeach; ?>
        </div>

        <div id="posts-sentinel" style="height:1px"></div>
        <div id="posts-loading" style="display:none;text-align:center;padding:1.5rem 0;color:var(--text-muted);font-size:.85rem">Loading…</div>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="meta">
            <span>&copy; <?= (new DateTime('now', $local_tz))->format('Y') ?> <?= htmlspecialchars($site_name) ?></span>
            <span class="dot">·</span>
            <span>v<?= htmlspecialchars(APP_VERSION) ?></span>
        </div>
        <?php if (!empty($tlMonths)): ?>
        <details>
            <summary>Archive</summary>
            <div class="archive-list">
                <?php foreach ($tlMonths as $row):
                    [$y, $m] = explode('-', $row['ym']);
                    $label = date('M Y', mktime(0, 0, 0, (int)$m, 1, (int)$y));
                ?>
                <a href="/?month=<?= htmlspecialchars($row['ym']) ?>"<?= $monthFilter === $row['ym'] ? ' style="color:var(--accent)"' : '' ?>>
                    <?= htmlspecialchars($label) ?><span class="count">(<?= (int)$row['cnt'] ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
    </footer>

</main>

<script>
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
    }, { rootMargin: '400px' });
    obs.observe(sentinel);
})();
</script>

<?php if ($user): ?>
<script>
const _csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function toggleComments(postId) {
    const body = document.getElementById('cmts-body-' + postId);
    const hdr  = body.previousElementSibling;
    body.classList.toggle('open');
    hdr.classList.toggle('open', body.classList.contains('open'));
}

function editComment(id, btn) {
    const bodyEl = document.getElementById('cbody-' + id);
    const orig = bodyEl.textContent;
    bodyEl.dataset.orig = orig;
    bodyEl.innerHTML = '';
    const form = document.createElement('form');
    form.method = 'post'; form.action = '/comment.php'; form.style.cssText = 'margin:0';
    form.innerHTML =
      '<input type="hidden" name="csrf_token" value="' + _csrf + '">' +
      '<input type="hidden" name="action" value="edit">' +
      '<input type="hidden" name="comment_id" value="' + id + '">' +
      '<input type="hidden" name="redirect" value="' + location.pathname + location.search + '">' +
      '<textarea name="body" required maxlength="2000" style="width:100%;min-height:80px;padding:.5rem .75rem;border:1px solid var(--accent);border-radius:8px;font-family:inherit;font-size:.95rem;background:var(--bg);color:var(--text)">' +
      orig.replace(/</g, '&lt;') + '</textarea>' +
      '<div style="display:flex;gap:.5rem;margin-top:.5rem">' +
        '<button type="submit" class="btn btn-primary btn-sm">Save</button>' +
        '<button type="button" class="btn btn-ghost btn-sm" onclick="cancelEdit(' + id + ')">Cancel</button>' +
      '</div>';
    bodyEl.appendChild(form);
    form.querySelector('textarea').focus();
}

function cancelEdit(id) {
    const bodyEl = document.getElementById('cbody-' + id);
    bodyEl.textContent = bodyEl.dataset.orig || '';
}

function onSelChange(postId) {
    const sec = document.getElementById('csec-' + postId);
    const all = sec.querySelectorAll('.comment-sel');
    const checked = sec.querySelectorAll('.comment-sel:checked');
    const bar = document.getElementById('bulk-' + postId);
    const cnt = document.getElementById('bulkcount-' + postId);
    bar.classList.toggle('show', checked.length > 0);
    cnt.textContent = checked.length + ' selected';
}

function clearSel(postId) {
    document.getElementById('csec-' + postId).querySelectorAll('.comment-sel').forEach(c => c.checked = false);
    onSelChange(postId);
}

function prepareBulkDelete(postId, form) {
    const ids = Array.from(document.getElementById('csec-' + postId).querySelectorAll('.comment-sel:checked')).map(c => parseInt(c.value));
    if (!ids.length) return false;
    if (!confirm('Delete ' + ids.length + ' comment' + (ids.length !== 1 ? 's' : '') + '?')) return false;
    form.querySelector('[name="comment_ids"]').value = JSON.stringify(ids);
    return true;
}
</script>
<?php endif; ?>

</body>
</html>
