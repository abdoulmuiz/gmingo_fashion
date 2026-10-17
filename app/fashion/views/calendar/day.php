<section class="card">
    <p><a href="<?= e(url($day['back'])) ?>"><?= e(t('calendar')) ?></a></p>
    <h1><?= e($day['title']) ?></h1>
    <?php if ($day['today']): ?>
        <p class="cal-today-mark"><?= e(t('calendar_today')) ?></p>
    <?php endif; ?>
    <?php if ($day['items'] === []): ?>
        <p><?= e(t('no_fittings')) ?></p>
    <?php endif; ?>
    <?php foreach ($day['items'] as $item): ?>
        <a class="line cal-item <?= e($item['class']) ?>" href="<?= e(url($item['href'])) ?>">
            <span class="cal-kind"><?= e(t($item['label_key'])) ?></span>
            <strong><?= e($item['text']) ?></strong>
            <?php if ($item['note'] !== ''): ?>
                <span><?= e($item['note']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</section>
