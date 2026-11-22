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
$title = (string) $day['title'];
$subtitle = $day['today'] ? t('calendar_today') : null;
$back_href = (string) $day['back'];
$back_label = t('calendar');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card cal-agenda" aria-label="<?= e($day['title']) ?>">
    <?php if ($day['items'] === []): ?>
        <?php
        $title = t('no_fittings');
        $help = null;
        $icon = 'calendar';
        $cta_href = '/orders';
        $cta_label = t('orders');
        require __DIR__ . '/../partials/ui_empty_state.php';
        ?>
    <?php else: ?>
        <ul class="cal-agenda-list">
            <?php foreach ($day['items'] as $item): ?>
                <li>
                    <a class="cal-item cal-agenda-row <?= e($item['class']) ?>" href="<?= e(url($item['href'])) ?>">
                        <div class="cal-agenda-row-head">
                            <?php
                            $label = t($item['label_key']);
                            $tone = $calBadgeTone((string) $item['class']);
                            require __DIR__ . '/../partials/ui_status_badge.php';
                            ?>
                            <strong class="cal-agenda-title"><?= e($item['text']) ?></strong>
                        </div>
                        <?php if ($item['note'] !== ''): ?>
                            <p class="cal-item-note"><?= e($item['note']) ?></p>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>