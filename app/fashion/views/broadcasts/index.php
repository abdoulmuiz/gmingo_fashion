<?php
/** @var array $broadcasts */
/** @var array $errors */
/** @var array $old */
$title = t('broadcasts');
$subtitle = t('broadcasts_help');
$back_href = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<div class="detail-layout broadcasts-layout">
    <div class="detail-main">
        <section class="card form-sheet broadcasts-compose">
            <h2><?= e(t('broadcast_send')) ?></h2>
            <p class="broadcast-audience">
                <span class="orders-badge is-purple"><?= e(t('broadcast_recipients')) ?></span>
                <span class="ui-help"><?= e(t('broadcasts_help')) ?></span>
            </p>
            <form method="post" action="<?= e(url('/broadcasts')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label class="span-2"><?= e(t('title')) ?>
                    <input name="title" value="<?= e((string) ($old['title'] ?? '')) ?>" required>
                </label>
                <?php if (isset($errors['title'])): ?>
                    <p class="ui-error span-2"><?= e(t($errors['title'])) ?></p>
                <?php endif; ?>
                <label class="span-2"><?= e(t('broadcast_body')) ?>
                    <textarea name="body" rows="5" required><?= e((string) ($old['body'] ?? '')) ?></textarea>
                </label>
                <?php if (isset($errors['body'])): ?>
                    <p class="ui-error span-2"><?= e(t($errors['body'])) ?></p>
                <?php endif; ?>
                <div class="span-2 form-sheet-footer" style="position:static;background:none;border:0;padding-bottom:0">
                    <button class="btn" type="submit"><?= e(t('broadcast_send')) ?></button>
                </div>
            </form>
        </section>
    </div>
    <aside class="detail-side">
        <section class="card broadcasts-history">
            <h2><?= e(t('broadcasts')) ?></h2>
            <?php if ($broadcasts === []): ?>
                <?php
                $title = t('broadcasts_empty');
                $help = null;
                $icon = 'mail';
                $cta_href = null;
                $cta_label = null;
                require __DIR__ . '/../partials/ui_empty_state.php';
                ?>
            <?php else: ?>
                <ul class="broadcast-list">
                    <?php foreach ($broadcasts as $broadcast): ?>
                        <li class="broadcast-item">
                            <header class="broadcast-item-head">
                                <strong><?= e($broadcast['title']) ?></strong>
                                <span class="orders-chip-count" title="<?= e(t('broadcast_recipients')) ?>">
                                    <?= e(number_format((int) $broadcast['recipient_count'])) ?>
                                </span>
                            </header>
                            <p class="broadcast-item-body"><?= e($broadcast['body']) ?></p>
                            <p class="meta">
                                <span class="orders-badge is-blue"><?= e(t('broadcast_recipients')) ?></span>
                                <span><?= e((string) $broadcast['recipient_count']) ?> · <?= e($broadcast['created_at']) ?></span>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>