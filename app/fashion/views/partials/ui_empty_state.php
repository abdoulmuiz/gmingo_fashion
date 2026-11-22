<?php
/**
 * @var string $title
 * @var string|null $help
 * @var string|null $icon
 * @var string|null $cta_href
 * @var string|null $cta_label
 */
$help = $help ?? null;
$icon = $icon ?? 'package';
$cta_href = $cta_href ?? null;
$cta_label = $cta_label ?? null;
?>
<section class="ui-empty card">
    <span class="ui-empty-icon" aria-hidden="true"><?= ui_icon($icon, 'ui-icon') ?></span>
    <h2><?= e($title) ?></h2>
    <?php if ($help !== null && $help !== ''): ?>
        <p><?= e($help) ?></p>
    <?php endif; ?>
    <?php if ($cta_href !== null && $cta_label !== null): ?>
        <a class="btn" href="<?= e(url($cta_href)) ?>"><?= e($cta_label) ?></a>
    <?php endif; ?>
</section>
