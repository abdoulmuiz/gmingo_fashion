<section class="card">
    <h1><?= e(t('message_templates')) ?></h1>
    <p><a href="<?= e(url('/messages')) ?>"><?= e(t('messages')) ?></a></p>
    <form method="post" action="<?= e(url('/messages/templates')) ?>">
        <?= csrf_field() ?>
        <?php foreach ($templates as $template): ?>
            <h2><?= e($template['label']) ?></h2>
            <?php foreach ($template['languages'] as $language): ?>
                <label><?= e($language['label']) ?>
                    <textarea name="body[<?= e($template['key']) ?>][<?= e($language['code']) ?>]" maxlength="2000" rows="4"><?= e($language['body']) ?></textarea>
                </label>
                <?php if (!in_array($language['code'], ['en', 'ha'], true) && $language['body'] === ''): ?>
                    <p class="meta"><?= e(t('template_add_later')) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
