<?php
/**
 * Shared site footer — the app-shell's pinned bottom bar.
 * Self-contained: only needs APP_VERSION (from version.php, loaded via auth.php)
 * and get_setting(). Render it as the last body-level element on a page.
 */
$__f_year = (new DateTime('now', new DateTimeZone(get_setting('timezone', 'UTC'))))->format('Y');
$__f_name = get_setting('site_name', 'SimpleBlog');
?>
<footer class="site-footer">
    <div class="meta">
        <span>&copy; <?= htmlspecialchars($__f_year) ?> <?= htmlspecialchars($__f_name) ?></span>
        <span class="dot">·</span>
        <span>v<?= htmlspecialchars(APP_VERSION) ?></span>
        <span class="dot">·</span>
        <a href="https://github.com/Isorgcom/SimpleBlog" target="_blank" rel="noopener">Powered By <strong>SimpleBlog</strong></a>
    </div>
</footer>
