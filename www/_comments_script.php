<?php /* Shared comment interactivity (toggle, inline edit, bulk-delete). Needs $user, $csrf, csp_nonce(). Uses event delegation so it also covers infinite-scroll chunks. */ ?>
<?php if ($user): ?>
<script nonce="<?= csp_nonce() ?>">
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
        '<button type="button" class="btn btn-ghost btn-sm" data-cancel-edit="' + id + '">Cancel</button>' +
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

// CSP-safe event delegation on document — also catches infinite-scroll chunks.
document.addEventListener('click', function (e) {
    var t;
    if ((t = e.target.closest('.comments-heading')))        { toggleComments(+t.dataset.post); }
    else if ((t = e.target.closest('[data-clear-sel]')))    { clearSel(+t.dataset.post); }
    else if ((t = e.target.closest('[data-edit-comment]'))) { editComment(+t.dataset.editComment, t); }
    else if ((t = e.target.closest('[data-cancel-edit]')))  { cancelEdit(+t.dataset.cancelEdit); }
});
document.addEventListener('change', function (e) {
    if (e.target.matches('.comment-sel')) onSelChange(+e.target.dataset.post);
});
document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-bulk-delete]');
    if (f && !prepareBulkDelete(+f.dataset.post, f)) e.preventDefault();
});
</script>
<?php endif; ?>
