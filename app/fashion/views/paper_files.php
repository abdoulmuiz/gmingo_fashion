<p class="actions">
    <a class="btn btn-small" href="<?= e($paper_png) ?>"><?= e(t('download_png')) ?></a>
    <a class="btn btn-small" href="<?= e($paper_pdf) ?>"><?= e(t('download_pdf')) ?></a>
    <a class="btn btn-small" href="<?= e($paper_print) ?>"><?= e(t('print')) ?></a>
    <button class="btn btn-small" type="button" data-share-file="<?= e($paper_pdf) ?>" data-share-name="<?= e($paper_name) ?>"><?= e(t('share_file')) ?></button>
</p>
