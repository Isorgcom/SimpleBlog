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
?>
<?php if ($_nav_bg || $_nav_text || $_accent): ?>
<style>
<?php if ($_accent): ?>:root{--accent:<?= htmlspecialchars($_accent,ENT_QUOTES) ?>;--accent-h:<?= htmlspecialchars($_accent,ENT_QUOTES) ?>;}<?php endif; ?>
<?php if ($_nav_bg): ?>nav{background:<?= htmlspecialchars($_nav_bg,ENT_QUOTES) ?> !important;}<?php endif; ?>
<?php if ($_nav_text): ?>nav .brand,nav .brand:hover{color:<?= htmlspecialchars($_nav_text,ENT_QUOTES) ?> !important;}<?php endif; ?>
</style>
<?php endif; ?>
<nav<?= $_nu ? ' class="nav-has-user"' : '' ?>>
    <div class="nav-top">
        <a class="brand" href="/">
            <?php if ($_banner): ?>
                <img src="<?= htmlspecialchars($_banner) ?>" alt="<?= htmlspecialchars($site_name) ?>"
                     style="max-height:38px;width:auto;display:block">
            <?php else: ?>
                <?= htmlspecialchars($site_name) ?>
            <?php endif; ?>
        </a>
        <div class="nav-user">
            <?php if ($_nu): ?>
                <span><?= htmlspecialchars($_nu['username']) ?></span>
                <div class="nav-dropdown-wrap">
                    <button class="nav-hamburger" onclick="this.nextElementSibling.classList.toggle('open')" title="Menu">&#9776;</button>
                    <div class="nav-dropdown">
                        <!-- Page links shown only on mobile (nav-links row hidden) -->
                        <a href="/" class="nav-mobile-link<?= $_active === 'home' ? ' active' : '' ?>">Home</a>
                        <a href="/calendar.php" class="nav-mobile-link<?= $_active === 'calendar' ? ' active' : '' ?>">Calendar</a>
                        <?php if ($_nu && $_nu['role'] === 'admin'): ?>
                        <a href="/admin_posts.php" class="nav-mobile-link<?= $_active === 'posts' ? ' active' : '' ?>">Posts</a>
                        <a href="/admin_settings.php" class="nav-mobile-link<?= $_active === 'site-settings' ? ' active' : '' ?>">Site Settings</a>
                        <?php endif; ?>
                        <div class="nav-mobile-divider"></div>
                        <a href="/settings.php"<?= $_active === 'settings' ? ' class="active"' : '' ?>>My Settings</a>
                        <a href="/logout.php" class="nav-dropdown-signout">Sign out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn-outline btn-sm">Login</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="nav-links">
        <a href="/"<?= $_active === 'home' ? ' class="active"' : '' ?>>Home</a>
        <a href="/calendar.php"<?= $_active === 'calendar' ? ' class="active"' : '' ?>>Calendar</a>
        <?php if ($_nu && $_nu['role'] === 'admin'): ?>
            <a href="/admin_posts.php"<?= $_active === 'posts' ? ' class="active"' : '' ?>>Posts</a>
            <a href="/admin_settings.php"<?= $_active === 'site-settings' ? ' class="active"' : '' ?>>Site Settings</a>
        <?php endif; ?>
    </div>
</nav>
<script src="/nav.js"></script>
