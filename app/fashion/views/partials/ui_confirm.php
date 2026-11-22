<?php
/**
 * Confirm dialog for destructive POSTs. Include once per page that needs it.
 * Forms opt in with data-confirm="message".
 */
?>
<dialog class="ui-confirm" id="ui-confirm" aria-labelledby="ui-confirm-title">
    <div class="ui-confirm-sheet">
        <h2 id="ui-confirm-title"><?= e(t('confirm')) ?></h2>
        <p class="ui-confirm-msg"></p>
        <div class="ui-confirm-actions">
            <button class="btn btn-quiet" type="button" data-ui-confirm-cancel><?= e(t('cancel')) ?></button>
            <button class="btn is-danger" type="button" data-ui-confirm-ok><?= e(t('confirm')) ?></button>
        </div>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('ui-confirm');
    if (!dialog || !dialog.showModal) return;
    var msg = dialog.querySelector('.ui-confirm-msg');
    var pending = null;
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) return;
        var text = form.getAttribute('data-confirm');
        if (!text || form.getAttribute('data-confirm-ok') === '1') return;
        ev.preventDefault();
        pending = form;
        if (msg) msg.textContent = text;
        dialog.showModal();
    }, true);
    function finish(ok) {
        dialog.close();
        if (ok && pending) {
            pending.setAttribute('data-confirm-ok', '1');
            if (typeof pending.requestSubmit === 'function') {
                pending.requestSubmit();
            } else {
                pending.submit();
            }
        }
        pending = null;
    }
    var cancelBtn = dialog.querySelector('[data-ui-confirm-cancel]');
    var okBtn = dialog.querySelector('[data-ui-confirm-ok]');
    if (cancelBtn) cancelBtn.addEventListener('click', function () { finish(false); });
    if (okBtn) okBtn.addEventListener('click', function () { finish(true); });
    dialog.addEventListener('cancel', function () { pending = null; });
})();
</script>
