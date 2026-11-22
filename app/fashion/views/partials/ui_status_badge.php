<?php
/**
 * @var string $label
 * @var string $tone  success|orange|blue|purple|danger
 */
$tone = (string) ($tone ?? 'blue');
$label = (string) ($label ?? '');
?>
<span class="orders-badge is-<?= e($tone) ?>"><?= e($label) ?></span>
