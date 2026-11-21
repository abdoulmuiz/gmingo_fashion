<?php
/** @var array $broadcasts */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('broadcasts')) ?></h1>
    <p class="meta"><?= e(t('broadcasts_help')) ?></p>
    <form method="post" action="<?= e(url('/broadcasts')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('title')) ?>
            <input name="title" value="<?= e((string) ($old['title'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('broadcast_body')) ?>
            <textarea name="body" rows="5" required><?= e((string) ($old['body'] ?? '')) ?></textarea>
        </label>
        <button class="btn" type="submit"><?= e(t('broadcast_send')) ?></button>
    </form>
</section>
<section class="card">
    <?php if ($broadcasts === []): ?>
        <p><?= e(t('broadcasts_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($broadcasts as $broadcast): ?>
            <div class="line">
                <strong><?= e($broadcast['title']) ?></strong>
                <span><?= e((string) $broadcast['recipient_count']) ?> <?= e(t('broadcast_recipients')) ?></span>
                <p class="meta"><?= e($broadcast['body']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
