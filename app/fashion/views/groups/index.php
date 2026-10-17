<section class="card">
    <div class="split">
        <h1><?= e(t('groups')) ?></h1>
        <a class="btn btn-small" href="<?= e(url('/groups/new')) ?>"><?= e(t('new_group')) ?></a>
    </div>
    <?php if ($groups === []): ?>
        <p><?= e(t('no_groups')) ?></p>
    <?php endif; ?>
</section>
<?php foreach ($groups as $group): ?>
        <a class="card link-card" href="<?= e(url('/groups/' . $group['id'])) ?>">
            <strong><?= e($group['name']) ?></strong>
            <span class="tag"><?= e(t($group['pay_mode'])) ?></span>
            <?php if ($group['event_date'] !== null): ?>
                <p class="meta"><?= e(t('event_date')) ?> <?= e(date('j M Y', strtotime((string) $group['event_date']))) ?></p>
            <?php endif; ?>
        </a>
<?php endforeach; ?>
<?php if ($has_previous || $has_next): ?>
        <nav class="pager" aria-label="<?= e(t('groups')) ?>">
            <?php if ($has_previous): ?>
                <a href="<?= e(url('/groups?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
            <?php endif; ?>
            <?php if ($has_next): ?>
                <a href="<?= e(url('/groups?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
