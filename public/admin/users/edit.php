<?php
/**
 * MyEvent Admin - Edit a user's name, email, category and role.
 */
require_once __DIR__ . '/../../../config.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$user = db_all($conn, 'SELECT user_id, name, email, phone, organization, category, role, created_at FROM users WHERE user_id = ?', 'i', [$id])[0] ?? null;
if (!$user) {
    flash('error', __('User not found.'));
    redirect('admin/users/manage.php');
}

$error = '';
$original = $user;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role     = (string) ($_POST['role'] ?? '');
    $category = (string) ($_POST['category'] ?? '');
    $user = array_merge($user, compact('name', 'email', 'role', 'category'));

    if (is_demo_account($original['email'])) {
        $error = __('Demo accounts cannot be edited or deleted, so every visitor can still sign in.');
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('Enter a name and a valid email address.');
    } elseif (!in_array($role, ['User', 'Admin'], true) || !in_array($category, ['Student', 'Staff', 'Public'], true)) {
        $error = __('Choose a valid role and category.');
    } elseif ($id === current_user_id() && $role !== 'Admin') {
        $error = __('You cannot remove your own admin role. Ask another administrator to do it.');
    } else {
        $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, role = ?, category = ? WHERE user_id = ?');
        $stmt->bind_param('ssssi', $name, $email, $role, $category, $id);
        try {
            $stmt->execute();
            $changes = [];
            foreach (['name', 'email', 'category', 'role'] as $f) {
                if ($original[$f] !== $$f) {
                    $changes[] = "$f: {$original[$f]} -> {$$f}";
                }
            }
            audit($conn, $original['role'] !== $role ? 'user.role_change' : 'user.update', 'user', $id, implode('; ', $changes) ?: '—');
            flash('success', __('Saved changes to {name}.', ['name' => $name]));
            redirect('admin/users/manage.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== 1062) {
                throw $ex;
            }
            $error = __('Another account already uses that email address.');
        }
    }
}

$page_title = __('Edit user');
$nav_active = 'admin_users';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<a class="back-link" href="manage.php"><i class="fa-solid fa-arrow-left"></i> <?= t('Users') ?></a>
<div class="page-header card-narrow">
    <div>
        <h1><?= e($user['name']) ?></h1>
        <p><?= t('Member since {date}', ['date' => ldate('j F Y', $user['created_at'])]) ?><?= $user['organization'] ? ', ' . e($user['organization']) : '' ?>.</p>
    </div>
</div>

<?php if ($error): ?><div class="flash flash-error card-narrow" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="POST" class="card card-narrow">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-grid">
            <div class="field">
                <label for="u-name"><?= t('Full name') ?></label>
                <input id="u-name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="255" required>
            </div>
            <div class="field">
                <label for="u-email"><?= t('Email') ?></label>
                <input id="u-email" type="email" name="email" value="<?= e($user['email']) ?>" maxlength="255" required>
            </div>
            <div class="field">
                <label for="u-cat"><?= t('Category') ?></label>
                <select id="u-cat" name="category">
                    <?php foreach (['Student', 'Staff', 'Public'] as $c): ?>
                        <option value="<?= e($c) ?>"<?= $user['category'] === $c ? ' selected' : '' ?>><?= e(status_label($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="u-role"><?= t('Role') ?></label>
                <select id="u-role" name="role">
                    <option value="User"<?= $user['role'] === 'User' ? ' selected' : '' ?>><?= t('User: can join and organise events') ?></option>
                    <option value="Admin"<?= $user['role'] === 'Admin' ? ' selected' : '' ?>><?= t('Admin: full access to every event and account') ?></option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-ghost" href="manage.php"><?= t('Cancel') ?></a>
            <button type="submit" class="btn btn-primary"><?= t('Save changes') ?></button>
        </div>
    </div>
</form>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
