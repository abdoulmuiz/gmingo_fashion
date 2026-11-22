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

$title = t($isEdit ? 'staff_edit' : 'staff_add');
$back_href = '/staff';
$back_label = t('staff');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card form-sheet">
    <?php
    $page_toasts = is_array($page_toasts ?? null) ? $page_toasts : [];
    if (!empty($errors['form'])) {
        $page_toasts[] = ['type' => 'error', 'message' => t((string) $errors['form'])];
    }
    if ($isEdit && !$person['active']) {
        $page_toasts[] = ['type' => 'warning', 'message' => t('staff_inactive')];
    }
    ?>
    <form method="post" action="<?= e(url($isEdit ? '/staff/' . $person['id'] : '/staff/new')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="form-sections">
            <div class="form-grid">
                <div class="span-2">
                    <label class="ui-label" for="field-name"><?= e(t('name')) ?> *</label>
                    <input id="field-name" name="name" value="<?= e($name) ?>" required>
                    <?php if (!empty($errors['name'])): ?><p class="ui-error"><?= e(t((string) $errors['name'])) ?></p><?php endif; ?>
                </div>
                <?php if (!$isEdit): ?>
                    <div>
                        <label class="ui-label" for="field-email"><?= e(t('email')) ?> *</label>
                        <input id="field-email" name="email" type="email" value="<?= e($email) ?>" required>
                        <?php if (!empty($errors['email'])): ?><p class="ui-error"><?= e(t((string) $errors['email'])) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <label class="ui-label" for="field-password"><?= e(t('password')) ?> *</label>
                        <input id="field-password" name="password" type="password" required>
                        <?php if (!empty($errors['password'])): ?><p class="ui-error"><?= e(t((string) $errors['password'])) ?></p><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="span-2">
                        <p class="meta"><strong><?= e(t('email')) ?>:</strong> <?= e($email) ?></p>
                    </div>
                    <div class="span-2">
                        <label class="ui-label" for="field-password"><?= e(t('staff_password_optional')) ?></label>
                        <input id="field-password" name="password" type="password">
                        <?php if (!empty($errors['password'])): ?><p class="ui-error"><?= e(t((string) $errors['password'])) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div>
                    <label class="ui-label" for="field-role"><?= e(t('staff_role')) ?></label>
                    <select id="field-role" name="role" <?= $isEdit && ($person['role'] ?? '') === 'owner' ? 'disabled' : '' ?>>
                        <?php foreach ($roles as $option): ?>
                            <option value="<?= e($option) ?>" <?= $role === $option ? 'selected' : '' ?>><?= e(t('role_user_' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit && ($person['role'] ?? '') === 'owner'): ?>
                        <input type="hidden" name="role" value="owner">
                    <?php endif; ?>
                    <?php if (!empty($errors['role'])): ?><p class="ui-error"><?= e(t((string) $errors['role'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-label" for="field-phone"><?= e(t('phone')) ?></label>
                    <input id="field-phone" name="phone" value="<?= e($phone) ?>">
                    <?php if (!empty($errors['phone'])): ?><p class="ui-error"><?= e(t((string) $errors['phone'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-label" for="field-started_on"><?= e(t('staff_started_on')) ?></label>
                    <input id="field-started_on" name="started_on" type="date" value="<?= e($startedOn) ?>">
                    <?php if (!empty($errors['started_on'])): ?><p class="ui-error"><?= e(t((string) $errors['started_on'])) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="form-sheet-footer">
            <a class="btn btn-quiet" href="<?= e(url('/staff')) ?>"><?= e(t('cancel')) ?></a>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </div>
    </form>
    <?php if ($isEdit): ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line, #e8e8e8)">
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
        </div>
    <?php endif; ?>
</section>