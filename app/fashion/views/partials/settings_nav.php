<?php
/**
 * Left subnav for settings sections. Mobile: select dropdown.
 */
$settingsLinks = [
    ['/settings', 'settings'],
];
foreach (menu_links('settings') as [$path, $label]) {
    $settingsLinks[] = [$path, $label];
}
$currentPath = request_path();
?>
<nav class="settings-nav" aria-label="<?= e(t('settings')) ?>">
    <div class="settings-nav-desktop">
        <?php foreach ($settingsLinks as [$path, $label]): ?>
            <?php
            $active = $path === '/settings'
                ? ($currentPath === '/settings')
                : str_starts_with($currentPath, $path);
            ?>
            <a class="settings-nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"><?= e(t($label)) ?></a>
        <?php endforeach; ?>
    </div>
    <form class="settings-nav-mobile" method="get" action="" data-settings-jump>
        <label class="ui-label" for="settings-jump"><?= e(t('settings')) ?></label>
        <select id="settings-jump" name="settings_jump">
            <?php foreach ($settingsLinks as [$path, $label]): ?>
                <?php
                $active = $path === '/settings'
                    ? ($currentPath === '/settings')
                    : str_starts_with($currentPath, $path);
                ?>
                <option value="<?= e(url($path)) ?>" <?= $active ? 'selected' : '' ?>><?= e(t($label)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</nav>
<script>
(function () {
    var form = document.querySelector('[data-settings-jump]');
    if (!form) return;
    var select = form.querySelector('select');
    if (!select) return;
    select.addEventListener('change', function () {
        if (select.value) window.location.href = select.value;
    });
})();
</script>
