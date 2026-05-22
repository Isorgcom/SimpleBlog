<?php /* Shared <head> partial: theme bootstrap (avoids FOUC). toggleTheme() lives in nav.js. Pages require this between <head> tags after their <link rel="stylesheet"> */ ?>
<?php $__palette = get_setting('site_theme', 'editorial'); ?>
<script nonce="<?= csp_nonce() ?>">
(function(){
  document.documentElement.setAttribute('data-palette', <?= json_encode($__palette) ?>);
  try {
    var t = localStorage.getItem('theme');
    if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
  } catch(_) {}
})();
</script>
