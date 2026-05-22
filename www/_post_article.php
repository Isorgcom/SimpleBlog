<?php
/**
 * Single post "article" card — shared by index.php, posts_chunk.php, post.php.
 * Expects in scope:
 *   $post       — row with id, title, content, created_at, pinned, slug
 *   $comments   — array of comment rows for this post
 *   $isAdmin    — bool
 *   $user       — array|null (current user)
 *   $csrf       — CSRF token (string; '' if logged out)
 *   $local_tz   — DateTimeZone
 *   $redir      — where comment add/delete should redirect back to
 *   $permalink  — canonical URL for this post (/post/<slug>)
 */
$dt = (new DateTime($post['created_at'], new DateTimeZone('UTC')))->setTimezone($local_tz);
$rt = reading_time($post['content']);
?>
<article class="post-article<?= $post['pinned'] ? ' is-featured' : '' ?>" id="post-<?= (int)$post['id'] ?>">
    <div class="post-meta">
        <?php if ($post['pinned']): ?><span class="pin-chip">📌 Pinned</span><?php endif; ?>
        <span><?= htmlspecialchars($dt->format('F j, Y')) ?></span>
        <span class="dot">·</span>
        <span><?= $rt ?> min read</span>
        <a class="post-permalink" href="<?= htmlspecialchars($permalink) ?>" title="Permalink to this post" aria-label="Permalink to this post">🔗</a>
        <?php if ($isAdmin): ?>
        <div class="post-actions">
            <a href="/admin_posts.php?edit=<?= (int)$post['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <form method="post" action="/admin_posts.php" style="margin:0" data-confirm="Delete this post?">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <h2 class="post-title"><a href="<?= htmlspecialchars($permalink) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
    <div class="post-body"><?= sanitize_html($post['content']) ?></div>

    <div class="comments-section" id="csec-<?= (int)$post['id'] ?>">
        <button type="button" class="comments-heading" data-post="<?= (int)$post['id'] ?>">
            <span class="cmts-chevron">▶</span>
            <?= count($comments) ?> Comment<?= count($comments) !== 1 ? 's' : '' ?>
        </button>
        <div class="comments-body" id="cmts-body-<?= (int)$post['id'] ?>">
            <?php if ($isAdmin && count($comments) > 0): ?>
            <div class="bulk-bar" id="bulk-<?= (int)$post['id'] ?>">
                <span id="bulkcount-<?= (int)$post['id'] ?>">0 selected</span>
                <span class="grow"></span>
                <form method="post" action="/comment.php" style="margin:0;display:contents"
                      data-bulk-delete data-post="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="comment_ids" value="">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redir) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete selected</button>
                </form>
                <button type="button" class="btn btn-ghost btn-sm" data-clear-sel data-post="<?= (int)$post['id'] ?>">Cancel</button>
            </div>
            <?php endif; ?>

            <?php foreach ($comments as $c): ?>
            <div class="comment" id="cmt-<?= (int)$c['id'] ?>">
                <?php if ($isAdmin): ?>
                <input type="checkbox" class="comment-sel" value="<?= (int)$c['id'] ?>" data-post="<?= (int)$post['id'] ?>">
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
                        <button type="button" data-edit-comment="<?= (int)$c['id'] ?>">Edit</button>
                        <form method="post" action="/comment.php" style="margin:0;display:contents" data-confirm="Delete this comment?">
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
