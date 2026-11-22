<?php
/**
 * @var string $name
 * @var string|null $initials
 */
$name = (string) ($name ?? '');
$initials = $initials ?? null;
if ($initials === null || $initials === '') {
    $initials = function_exists('customer_initials') ? customer_initials($name) : '?';
}
?>
<span class="customers-avatar" aria-hidden="true"><?= e((string) $initials) ?></span>
