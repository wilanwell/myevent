<?php
/**
 * MyEvent Admin - Edit a user's name, email, category and role.
 */
require_once __DIR__ . '/../../config.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$user = db_all($conn, 'SELECT user_id, name, email, phone, organization, category, role, created_at FROM users WHERE user_id = ?', 'i', [$id])[0] ?? null;
if (!$user) {
    flash('error', 'User not found.');
    redirect('admin/users/manage.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role     = (string) ($_POST['role'] ?? '');
    $category = (string) ($_POST['category'] ?? '');
    $user = array_merge($user, compact('name', 'email', 'role', 'category'));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a name and a valid email address.';
    } elseif (!in_array($role, ['User', 'Admin'], true) || !in_array($category, ['Student', 'Staff', 'Public'], true)) {
        $error = 'Choose a valid role and category.';
    } elseif ($id === current_user_id() && $role !== 'Admin') {
        $error = 'You cannot remove your own admin role. Ask another administrator to do it.';
    } else {
        $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, role = ?, category = ? WHERE user_id = ?');
        $stmt->bind_param('ssssi', $name, $email, $role, $category, $id);
        try {
            $stmt->execute();
            flash('success', 'Saved changes to ' . $name . '.');
            redirect('admin/users/manage.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== 1062) {
                throw $ex;
            }
            $error = 'Another account already uses that email address.';
        }
    }
}

$page_title = 'Edit user';
$nav_active = 'admin_users';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<a class="back-link" href="manage.php"><i class="fa-solid fa-arrow-left"></i> Users</a>
<div class="page-header">
    <div>
        <h1><?= e($user['name']) ?></h1>
        <p>Member since <?= e(date('j F Y', strtotime($user['created_at']))) ?><?= $user['organization'] ? ', ' . e($user['organization']) : '' ?>.</p>
    </div>
</div>

<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="POST" class="card card-narrow">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-grid">
            <div class="field">
                <label for="u-name">Full name</label>
                <input id="u-name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="255" required>
            </div>
            <div class="field">
                <label for="u-email">Email</label>
                <input id="u-email" type="email" name="email" value="<?= e($user['email']) ?>" maxlength="255" required>
            </div>
            <div class="field">
                <label for="u-cat">Category</label>
                <select id="u-cat" name="category">
                    <?php foreach (['Student', 'Staff', 'Public'] as $c): ?>
                        <option<?= $user['category'] === $c ? ' selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="u-role">Role</label>
                <select id="u-role" name="role">
                    <option value="User"<?= $user['role'] === 'User' ? ' selected' : '' ?>>User: can join and organise events</option>
                    <option value="Admin"<?= $user['role'] === 'Admin' ? ' selected' : '' ?>>Admin: full access to every event and account</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-ghost" href="manage.php">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </div>
</form>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
