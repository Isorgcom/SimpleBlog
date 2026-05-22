// Shared, CSP-safe global behaviors (loaded on every page via _nav.php).
// No inline handlers anywhere — everything is wired here or in page scripts.

// Theme toggle (initial theme is set inline in _head.php to avoid FOUC).
function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('theme', next); } catch (_) {}
}

document.addEventListener('click', function (e) {
    // Theme toggle button
    if (e.target.closest('[data-action="toggle-theme"]')) {
        toggleTheme();
        return;
    }
    // User menu open/close
    var menuToggle = e.target.closest('[data-action="toggle-user-menu"]');
    if (menuToggle) {
        var menu = menuToggle.closest('.user-menu');
        if (menu) menu.classList.toggle('open');
        return;
    }
    // Click outside any user menu closes open ones
    if (!e.target.closest('.user-menu')) {
        document.querySelectorAll('.user-menu.open').forEach(function (m) {
            m.classList.remove('open');
        });
    }
});

// Global confirm-before-submit: any <form data-confirm="message"> prompts first.
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.matches && form.matches('[data-confirm]')) {
        if (!confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    }
});
