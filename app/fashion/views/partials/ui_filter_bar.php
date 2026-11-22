<?php
/**
 * @var string $action  form action path
 * @var list<array{key:string,label:string,count?:int|null,active?:bool}> $chips
 * @var string $chip_param
 * @var string|null $q
 * @var string|null $placeholder
 * @var array<string,string> $hidden  extra hidden fields
 * @var string|null $sort_html  optional pre-rendered sort controls
 */
$action = (string) ($action ?? '');
$chips = is_array($chips ?? null) ? $chips : [];
$chip_param = (string) ($chip_param ?? 'filter');
$q = (string) ($q ?? '');
$placeholder = (string) ($placeholder ?? t('search'));
$hidden = is_array($hidden ?? null) ? $hidden : [];
$sort_html = $sort_html ?? '';
?>
<form class="ui-filter-bar" method="get" action="<?= e(url($action)) ?>">
    <?php foreach ($hidden as $hk => $hv): ?>
        <input type="hidden" name="<?= e((string) $hk) ?>" value="<?= e((string) $hv) ?>">
    <?php endforeach; ?>
    <?php if ($chips !== []): ?>
        <div class="orders-chips" role="tablist">
            <?php foreach ($chips as $chip): ?>
                <?php
                $active = !empty($chip['active']);
                $hrefParams = array_merge($hidden, [$chip_param => (string) $chip['key']]);
                if ($q !== '') {
                    $hrefParams['q'] = $q;
                }
                $qs = http_build_query($hrefParams);
                ?>
                <a
                    class="orders-chip<?= $active ? ' is-active' : '' ?>"
                    href="<?= e(url($action . ($qs !== '' ? '?' . $qs : ''))) ?>"
                    role="tab"
                    aria-selected="<?= $active ? 'true' : 'false' ?>"
                >
                    <span><?= e((string) $chip['label']) ?></span>
                    <?php if (array_key_exists('count', $chip) && $chip['count'] !== null): ?>
                        <em><?= e((string) (int) $chip['count']) ?></em>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="ui-filter-tools">
        <label class="orders-search">
            <span class="visually-hidden"><?= e($placeholder) ?></span>
            <?= ui_icon('search', 'ui-icon') ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholder) ?>">
        </label>
        <?= $sort_html ?>
        <button class="btn btn-quiet" type="submit"><?= e(t('search')) ?></button>
    </div>
</form>
