<?php
/**
 * @var string $name
 * @var string $label
 * @var string $type  text|tel|email|date|number|password|select|textarea|checkbox
 * @var string|null $value
 * @var string|null $error  translation key or message already translated
 * @var string|null $help
 * @var bool $required
 * @var bool $money  show ₦ prefix
 * @var list<array{value:string,label:string}>|null $options  for select
 * @var string|null $inputmode
 * @var string|null $attrs  extra raw attributes (already safe)
 */
$type = (string) ($type ?? 'text');
$value = (string) ($value ?? '');
$error = $error ?? null;
$help = $help ?? null;
$required = !empty($required);
$money = !empty($money);
$options = is_array($options ?? null) ? $options : [];
$inputmode = $inputmode ?? null;
$attrs = $attrs ?? '';
?>
<div class="ui-field<?= $error !== null && $error !== '' ? ' has-error' : '' ?>">
    <?php if ($type === 'checkbox'): ?>
        <label class="ui-check">
            <input type="checkbox" name="<?= e($name) ?>" value="1" <?= $value !== '' && $value !== '0' ? 'checked' : '' ?> <?= $required ? 'required' : '' ?> <?= $attrs ?>>
            <span><?= e($label) ?></span>
        </label>
    <?php else: ?>
        <label class="ui-label" for="field-<?= e($name) ?>"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
        <?php if ($type === 'select'): ?>
            <select id="field-<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>>
                <?php foreach ($options as $opt): ?>
                    <option value="<?= e((string) $opt['value']) ?>" <?= (string) $opt['value'] === $value ? 'selected' : '' ?>><?= e((string) $opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea id="field-<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>><?= e($value) ?></textarea>
        <?php else: ?>
            <div class="<?= $money ? 'ui-money' : 'ui-input-wrap' ?>">
                <?php if ($money): ?><span class="ui-money-prefix" aria-hidden="true">₦</span><?php endif; ?>
                <input
                    id="field-<?= e($name) ?>"
                    type="<?= e($type) ?>"
                    name="<?= e($name) ?>"
                    value="<?= e($value) ?>"
                    <?= $required ? 'required' : '' ?>
                    <?= $inputmode !== null ? 'inputmode="' . e($inputmode) . '"' : '' ?>
                    <?= $attrs ?>
                >
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($help !== null && $help !== ''): ?>
        <p class="ui-help"><?= e($help) ?></p>
    <?php endif; ?>
    <?php if ($error !== null && $error !== ''): ?>
        <p class="ui-error"><?= e($error) ?></p>
    <?php endif; ?>
</div>
