<?php
/**
 * @var list<array{label:string,value:string,tone?:string,hint?:string}> $cards
 */
$cards = is_array($cards ?? null) ? $cards : [];
if ($cards === []) {
    return;
}
?>
<section class="dash-kpi" aria-label="<?= e(t('summary') !== 'summary' ? t('summary') : 'Summary') ?>">
    <?php foreach ($cards as $card): ?>
        <article class="dash-kpi-card<?= !empty($card['tone']) ? ' is-' . e((string) $card['tone']) : '' ?>">
            <p class="dash-kpi-label"><?= e((string) ($card['label'] ?? '')) ?></p>
            <p class="dash-kpi-value"><?= e((string) ($card['value'] ?? '')) ?></p>
            <?php if (!empty($card['hint'])): ?>
                <p class="dash-kpi-hint"><?= e((string) $card['hint']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
