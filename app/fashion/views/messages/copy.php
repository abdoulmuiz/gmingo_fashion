<label><?= e(t('copy_message')) ?>
    <textarea readonly rows="4"><?= e($message['body']) ?></textarea>
</label>
<button class="btn btn-small" type="button" data-copy-message><?= e(t('copy_message')) ?></button>
<script>
document.querySelectorAll('[data-copy-message]').forEach(function (button) {
    if (button.getAttribute('data-copy-bound') === '1') {
        return;
    }
    button.setAttribute('data-copy-bound', '1');
    button.addEventListener('click', function () {
        var box = button.parentElement ? button.parentElement.querySelector('textarea') : null;
        if (!box && button.previousElementSibling) {
            box = button.previousElementSibling.querySelector('textarea');
        }
        if (!box) {
            return;
        }
        box.focus();
        box.select();
        var done = function () {
            if (window.toast) {
                window.toast.success(<?= json_encode(t('copied'), JSON_UNESCAPED_UNICODE) ?>);
            }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(box.value).then(done).catch(done);
        } else {
            done();
        }
    });
});
</script>
