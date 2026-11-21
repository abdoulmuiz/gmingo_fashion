<?php
/** @var array $people */
?>
<section class="card">
    <h1><?= e(t('staff')) ?></h1>
    <p class="meta"><?= e(t('staff_help')) ?></p>
    <p><a class="btn" href="<?= e(url('/staff/new')) ?>"><?= e(t('staff_add')) ?></a></p>
    <?php if ($people === []): ?>
        <p><?= e(t('staff_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($people as $person): ?>
            <a class="line" href="<?= e(url('/staff/' . $person['id'])) ?>">
                <strong><?= e((string) $person['name']) ?></strong>
                <span><?= e(t('role_user_' . (string) $person['role'])) ?></span>
                <span><?= e($person['active'] ? t('staff_active') : t('staff_inactive')) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
