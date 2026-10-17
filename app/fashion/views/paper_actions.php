<div class="print-actions no-print">
    <button class="btn btn-quiet btn-small" type="button" id="paper-a4"><?= e(t('paper_a4')) ?></button>
    <button class="btn btn-quiet btn-small" type="button" id="paper-narrow"><?= e(t('paper_narrow')) ?></button>
    <button class="btn" type="button" id="print-save"><?= e(t('print_save_pdf')) ?></button>
</div>
<script>
document.getElementById('paper-a4').addEventListener('click', function () {
    document.body.classList.remove('paper-thermal');
});
document.getElementById('paper-narrow').addEventListener('click', function () {
    document.body.classList.add('paper-thermal');
});
document.getElementById('print-save').addEventListener('click', function () {
    window.print();
});
</script>
