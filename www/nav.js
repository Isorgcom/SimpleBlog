document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
        document.querySelectorAll('.user-menu.open').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});
