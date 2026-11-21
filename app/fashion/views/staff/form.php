<?php
/** @var string $mode */
/** @var ?array $person */
/** @var array $roles */
/** @var array $errors */
/** @var array $old */
$isEdit = $mode === 'edit' && is_array($person);
$name = (string) ($old['name'] ?? ($person['name'] ?? ''));
$email = (string) ($old['email'] ?? ($person['email'] ?? ''));
$role = (string) ($old['role'] ?? ($person['role'] ?? 'staff'));
$phone = (string) ($old['phone'] ?? ($person['phone'] ?? ''));
$startedOn = (string) ($old['started_on'] ?? ($person['started_on'] ?? ''));
?>
<section class="card">
    <h1><?= e(t($isEdit ? 'staff_edit' : 'staff_add')) ?></h1>
    <p><a href="<?= e(url('/staff')) ?>"><?= e(t('staff')) ?></a></p>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <?php if ($isEdit && !$person['active']): ?>
        <p class="flash flash-error" role="alert"><?= e(t('staff_inactive')) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url($isEdit ? '/staff/' . $person['id'] : '/staff/new')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <label><?= e(t('name')) ?>
            <input name="name" value="<?= e($name) ?>" required>
            <?php if (!empty($errors['name'])): ?><span class="error"><?= e(t((string) $errors['name'])) ?></span><?php endif; ?>
        </label>
        <?php if (!$isEdit): ?>
            <label><?= e(t('email')) ?>
                <input name="email" type="email" value="<?= e($email) ?>" required>
                <?php if (!empty($errors['email'])): ?><span class="error"><?= e(t((string) $errors['email'])) ?></span><?php endif; ?>
            </label>
            <label><?= e(t('password')) ?>
                <input name="password" type="password" required>
                <?php if (!empty($errors['password'])): ?><span class="error"><?= e(t((string) $errors['password'])) ?></span><?php endif; ?>
            </label>
        <?php else: ?>
            <p><strong><?= e(t('email')) ?>:</strong> <?= e($email) ?></p>
            <label><?= e(t('staff_password_optional')) ?>
                <input name="password" type="password">
                <?php if (!empty($errors['password'])): ?><span class="error"><?= e(t((string) $errors['password'])) ?></span><?php endif; ?>
            </label>
        <?php endif; ?>
        <label><?= e(t('staff_role')) ?>
            <select name="role" <?= $isEdit && ($person['role'] ?? '') === 'owner' ? 'disabled' : '' ?>>
                <?php foreach ($roles as $option): ?>
                    <option value="<?= e($option) ?>" <?= $role === $option ? 'selected' : '' ?>><?= e(t('role_user_' . $option)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isEdit && ($person['role'] ?? '') === 'owner'): ?>
                <input type="hidden" name="role" value="owner">
            <?php endif; ?>
            <?php if (!empty($errors['role'])): ?><span class="error"><?= e(t((string) $errors['role'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('phone')) ?>
            <input name="phone" value="<?= e($phone) ?>">
            <?php if (!empty($errors['phone'])): ?><span class="error"><?= e(t((string) $errors['phone'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('staff_started_on')) ?>
            <input name="started_on" type="date" value="<?= e($startedOn) ?>">
            <?php if (!empty($errors['started_on'])): ?><span class="error"><?= e(t((string) $errors['started_on'])) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
    <?php if ($isEdit): ?>
        <?php if ($person['active']): ?>
            <form method="post" action="<?= e(url('/staff/' . $person['id'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deactivate">
                <button class="btn btn-quiet" type="submit"><?= e(t('staff_deactivate')) ?></button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/staff/' . $person['id'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reactivate">
                <button class="btn" type="submit"><?= e(t('staff_reactivate')) ?></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
