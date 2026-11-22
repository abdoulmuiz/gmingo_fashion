<?php
/**
 * @var list<array{id:string,name:string,code?:string,role?:string,state:string}> $steps
 *   state: done|current|todo
 */
$steps = is_array($steps ?? null) ? $steps : [];
?>
<ol class="ui-stepper" aria-label="<?= e(t('stage')) ?>">
    <?php foreach ($steps as $step): ?>
        <li class="ui-step is-<?= e((string) ($step['state'] ?? 'todo')) ?>">
            <span class="ui-step-dot" aria-hidden="true"></span>
            <span class="ui-step-label"><?= e((string) ($step['name'] ?? '')) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
