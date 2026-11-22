<?php
$title = t('more');
$subtitle = t('app_name');
$back_href = null;
$actions_html = '';
require __DIR__ . '/partials/ui_page_header.php';
?>
<?php if (current_user_id() !== null && !subscription_has_app_access()): ?>
    <?php
    $page_toasts = is_array($page_toasts ?? null) ? $page_toasts : [];
    $page_toasts[] = ['type' => 'error', 'message' => t('subscription_access_blocked')];
    ?>
    <section class="card">
        <p class="meta"><?= e(t('subscription_access_blocked')) ?></p>
        <p><a class="btn" href="<?= e(url('/account/subscription')) ?>"><?= e(t('subscription_account')) ?></a></p>
    </section>
<?php endif; ?>

<nav class="more-grid" aria-label="<?= e(t('more')) ?>">
    <?php foreach (menu_links('more') as [$path, $labelKey]): ?>
        <a class="more-tile card" href="<?= e(url($path)) ?>">
            <span class="more-tile-icon" aria-hidden="true"><?= ui_icon('chevron-right', 'ui-icon') ?></span>
            <span class="more-tile-label"><?= e(t($labelKey)) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<section class="card more-footer-panel">
    <form class="more-lang" method="post" action="<?= e(url('/language')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e(request_path()) ?>">
        <label><?= e(t('language')) ?>
            <select name="lang">
                <option value="en" <?= current_lang() === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                <option value="ha" <?= current_lang() === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
            </select>
        </label>
        <button class="btn btn-quiet" type="submit"><?= e(t('save')) ?></button>
    </form>
    <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-quiet" type="submit"><?= e(t('logout')) ?></button>
    </form>
</section>