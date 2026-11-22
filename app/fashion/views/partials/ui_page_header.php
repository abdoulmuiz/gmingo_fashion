<?php
/**
 * @var string $title
 * @var string|null $subtitle
 * @var string|null $back_href
 * @var string|null $back_label
 * @var string|null $actions_html  Pre-rendered HTML for the right-side actions
 */
$subtitle = $subtitle ?? null;
$back_href = $back_href ?? null;
$back_label = $back_label ?? t('back');
$actions_html = $actions_html ?? '';
?>
<header class="ui-page-header">
    <div class="ui-page-header-copy">
        <?php if ($back_href !== null && $back_href !== ''): ?>
            <a class="ui-back" href="<?= e(url($back_href)) ?>">
                <?= ui_icon('arrow-left', 'ui-icon') ?>
                <span><?= e($back_label) ?></span>
            </a>
        <?php endif; ?>
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle !== null && $subtitle !== ''): ?>
            <p><?= $subtitle ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actions_html !== ''): ?>
        <div class="ui-page-header-actions"><?= $actions_html ?></div>
    <?php endif; ?>
</header>
