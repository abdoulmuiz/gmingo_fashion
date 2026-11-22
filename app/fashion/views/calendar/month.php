<?php
$calBadgeTone = static function (string $class): string {
    if ($class === 'cal-due') {
        return 'orange';
    }
    if ($class === 'cal-done') {
        return 'success';
    }
    if ($class === 'cal-missed') {
        return 'danger';
    }

    return 'blue';
};
ob_start();
?>
<nav class="pager cal-pager" aria-label="<?= e(t('calendar')) ?>">
    <a class="btn btn-quiet btn-small" href="<?= e(url($calendar['previous'])) ?>"><?= e(t('previous')) ?></a>
    <a class="btn btn-quiet btn-small" href="<?= e(url($calendar['next'])) ?>"><?= e(t('next')) ?></a>
</nav>
<?php
$actions_html = ob_get_clean();
$title = (string) $calendar['title'];
$subtitle = null;
$back_href = '/orders';
$back_label = t('orders');
require __DIR__ . '/../partials/ui_page_header.php';
?>
<div class="cal cal-month card cal-panel" aria-label="<?= e($calendar['title']) ?>">
    <div class="cal-week cal-head" aria-hidden="true">
        <?php for ($weekday = 1; $weekday <= 7; $weekday++): ?>
            <div><?= e(t('weekday_' . (string) $weekday)) ?></div>
        <?php endfor; ?>
    </div>
    <?php foreach ($calendar['weeks'] as $week): ?>
        <div class="cal-week">
            <?php foreach ($week as $day): ?>
                <article
                    data-date="<?= e($day['date']) ?>"
                    class="cal-day<?= $day['in_month'] ? '' : ' cal-out' ?><?= $day['today'] ? ' cal-today' : '' ?>"
                >
                    <header class="cal-day-head">
                        <a class="cal-day-link" href="<?= e(url('/calendar/' . $day['date'])) ?>">
                            <span class="cal-number"><?= e($day['number']) ?></span>
                            <?php if ($day['today']): ?>
                                <span class="cal-today-mark"><?= e(t('calendar_today')) ?></span>
                            <?php endif; ?>
                        </a>
                    </header>
                    <div class="cal-day-items">
                        <?php foreach ($day['items'] as $item): ?>
                            <a class="cal-item cal-agenda-item <?= e($item['class']) ?>" href="<?= e(url($item['href'])) ?>">
                                <?php
                                $label = t($item['label_key']);
                                $tone = $calBadgeTone((string) $item['class']);
                                require __DIR__ . '/../partials/ui_status_badge.php';
                                ?>
                                <span class="cal-item-text"><?= e($item['text']) ?></span>
                                <?php if ($item['note'] !== ''): ?>
                                    <span class="cal-item-note"><?= e($item['note']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($day['hidden'] > 0): ?>
                            <a class="cal-more" href="<?= e(url($day['more_href'])) ?>"><?= e(t('calendar_more', ['count' => (string) $day['hidden']])) ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>