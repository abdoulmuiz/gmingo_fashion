<section class="card">
    <div class="split">
        <h1><?= e($calendar['title']) ?></h1>
        <a class="btn btn-small" href="<?= e(url('/orders')) ?>"><?= e(t('orders')) ?></a>
    </div>
    <nav class="pager" aria-label="<?= e(t('calendar')) ?>">
        <a href="<?= e(url($calendar['previous'])) ?>"><?= e(t('previous')) ?></a>
        <a href="<?= e(url($calendar['next'])) ?>"><?= e(t('next')) ?></a>
    </nav>
</section>
<div class="cal" aria-label="<?= e($calendar['title']) ?>">
    <div class="cal-week cal-head">
        <?php for ($weekday = 1; $weekday <= 7; $weekday++): ?>
            <div><?= e(t('weekday_' . (string) $weekday)) ?></div>
        <?php endfor; ?>
    </div>
    <?php foreach ($calendar['weeks'] as $week): ?>
        <div class="cal-week">
            <?php foreach ($week as $day): ?>
                <div data-date="<?= e($day['date']) ?>" class="cal-day<?= $day['in_month'] ? '' : ' cal-out' ?><?= $day['today'] ? ' cal-today' : '' ?>">
                    <span class="cal-number"><?= e($day['number']) ?></span>
                    <?php if ($day['today']): ?>
                        <span class="cal-today-mark"><?= e(t('calendar_today')) ?></span>
                    <?php endif; ?>
                    <?php foreach ($day['items'] as $item): ?>
                        <a class="cal-item <?= e($item['class']) ?>" href="<?= e(url($item['href'])) ?>">
                            <span class="cal-kind"><?= e(t($item['label_key'])) ?></span>
                            <span><?= e($item['text']) ?></span>
                            <?php if ($item['note'] !== ''): ?>
                                <span><?= e($item['note']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($day['hidden'] > 0): ?>
                        <a class="cal-more" href="<?= e(url($day['more_href'])) ?>"><?= e(t('calendar_more', ['count' => (string) $day['hidden']])) ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
