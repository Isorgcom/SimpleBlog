<?php
/**
 * Shared nav partial.
 * Before including, set:
 *   $nav_active — 'home' | 'posts' | 'site-settings' | 'settings' | ''
 *   $nav_user   — optional user array override; falls back to $current, then $user
 *   $site_name  — must already be set by the calling page
 */
$_nu       = $nav_user ?? $current ?? $user ?? null;
$_active   = $nav_active ?? '';
$_banner   = get_setting('banner_path', '');
$_nav_bg   = get_setting('nav_bg_color', '');
$_nav_text = get_setting('nav_text_color', '');
$_accent   = get_setting('accent_color', '');
// Admin-only "update available" dot on the Settings link.
$_show_update_dot = ($_nu && ($_nu['role'] ?? '') === 'admin'
                     && function_exists('update_available') && update_available());
?>
<?php if ($_nav_bg || $_nav_text || $_accent): ?>
<style>
<?php if ($_accent): ?>:root{--accent:<?= htmlspecialchars($_accent,ENT_QUOTES) ?> !important;--accent-h:<?= htmlspecialchars($_accent,ENT_QUOTES) ?> !important;}<?php endif; ?>
<?php if ($_nav_bg): ?>nav{background:<?= htmlspecialchars($_nav_bg,ENT_QUOTES) ?> !important;}<?php endif; ?>
<?php if ($_nav_text): ?>nav .brand,nav .brand:hover{color:<?= htmlspecialchars($_nav_text,ENT_QUOTES) ?> !important;}<?php endif; ?>
</style>
<?php endif; ?>
<nav>
    <div class="nav-top">
        <a class="brand" href="/">
            <?php if ($_banner): ?>
                <img src="<?= htmlspecialchars($_banner) ?>" alt="<?= htmlspecialchars($site_name) ?>">
            <?php else: ?>
                <?= htmlspecialchars($site_name) ?>
            <?php endif; ?>
        </a>
        <div class="nav-links">
            <a href="/"<?= $_active === 'home' ? ' class="active"' : '' ?>>Home</a>
            <?php if ($_nu && $_nu['role'] === 'admin'): ?>
                <a href="/admin_posts.php"<?= $_active === 'posts' ? ' class="active"' : '' ?>>Posts</a>
                <a href="/admin_settings.php"<?= $_active === 'site-settings' ? ' class="active"' : '' ?>>Settings<?php if ($_show_update_dot): ?> <span class="nav-update-dot" title="Update available: v<?= htmlspecialchars(get_setting('latest_version')) ?>"></span><?php endif; ?></a>
            <?php endif; ?>
        </div>
        <div class="nav-actions">
            <button class="theme-toggle" type="button" data-action="toggle-theme" aria-label="Toggle theme" title="Toggle theme">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <?php if ($_nu): ?>
                <div class="user-menu" id="userMenu">
                    <button class="user-menu-btn" type="button" data-action="toggle-user-menu">
                        <span class="avatar"><?= htmlspecialchars(strtoupper(substr($_nu['username'], 0, 1))) ?></span>
                        <span class="username"><?= htmlspecialchars($_nu['username']) ?></span>
                        <span style="font-size:.7rem;opacity:.6">▾</span>
                    </button>
                    <div class="user-menu-panel">
                        <a href="/"<?= $_active === 'home' ? ' style="color:var(--accent)"' : '' ?>>Home</a>
                        <?php if ($_nu['role'] === 'admin'): ?>
                            <a href="/admin_posts.php"<?= $_active === 'posts' ? ' style="color:var(--accent)"' : '' ?>>Posts</a>
                            <a href="/admin_settings.php"<?= $_active === 'site-settings' ? ' style="color:var(--accent)"' : '' ?>>Site Settings<?php if ($_show_update_dot): ?> <span class="nav-update-dot" title="Update available: v<?= htmlspecialchars(get_setting('latest_version')) ?>"></span><?php endif; ?></a>
                        <?php endif; ?>
                        <div class="menu-divider"></div>
                        <a href="/settings.php"<?= $_active === 'settings' ? ' style="color:var(--accent)"' : '' ?>>My Settings</a>
                        <a href="/logout.php" class="signout">Sign out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn-outline btn-sm">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script src="/nav.js"></script>
