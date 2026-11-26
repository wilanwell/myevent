<?php
/**
 * MyEvent Admin - Users: search, filter, export and manage accounts.
 */
require_once __DIR__ . '/../../config.php';

require_admin();

$q        = trim((string) ($_GET['q'] ?? ''));
$category = in_array($_GET['category'] ?? '', ['Student', 'Staff', 'Public'], true) ? $_GET['category'] : '';
$role     = in_array($_GET['role'] ?? '', ['Admin', 'User'], true) ? $_GET['role'] : '';

$where  = '1 = 1';
$types  = '';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.organization LIKE ?)';
    $types .= 'sss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($category !== '') {
    $where .= ' AND u.category = ?';
    $types .= 's';
    $params[] = $category;
}
if ($role !== '') {
    $where .= ' AND u.role = ?';
    $types .= 's';
    $params[] = $role;
}

// CSV export of the current filter
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all($conn,
        "SELECT u.user_id, u.name, u.email, u.phone, u.organization, u.category, u.role, u.created_at
         FROM users u WHERE $where ORDER BY u.created_at DESC", $types, $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . safe_filename('users_' . date('Y-m-d')) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['User ID', 'Name', 'Email', 'Phone', 'Organisation', 'Category', 'Role', 'Joined']);
    foreach ($rows as $row) {
        fputcsv($out, csv_row($row));
    }
    fclose($out);
    exit;
}

$total = (int) db_value($conn, "SELECT COUNT(*) FROM users u WHERE $where", $types, $params);
$p = paginate($total, 15);
$users = db_all($conn,
    "SELECT u.user_id, u.name, u.email, u.organization, u.category, u.role, u.created_at, u.profile_pic,
            (SELECT COUNT(*) FROM events e WHERE e.created_by = u.user_id) AS organised,
            (SELECT COUNT(*) FROM registrations r WHERE r.user_id = u.user_id) AS joined
     FROM users u WHERE $where
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$p['per_page'], $p['offset']]));

$me = current_user_id();
$page_title = __('Users');
$nav_active = 'admin_users';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= t('Users') ?></h1>
        <p><?= t('Everyone with a {app} account. Change roles or remove accounts here.', ['app' => APP_NAME]) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="<?= e(url_with(['export' => 'csv', 'page' => null])) ?>"><i class="fa-solid fa-file-csv"></i> <?= t('Export CSV') ?></a>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <form class="toolbar" method="GET" role="search">
            <label class="search-box">
                <span class="sr-only"><?= t('Search users') ?></span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Name, email or organisation') ?>">
            </label>
            <label class="sr-only" for="u-cat"><?= t('Category') ?></label>
            <select id="u-cat" class="input" name="category" data-autosubmit>
                <option value=""><?= t('All categories') ?></option>
                <?php foreach (['Student', 'Staff', 'Public'] as $c): ?>
                    <option value="<?= e($c) ?>"<?= $category === $c ? ' selected' : '' ?>><?= e(status_label($c)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="u-role"><?= t('Role') ?></label>
            <select id="u-role" class="input" name="role" data-autosubmit>
                <option value=""><?= t('All roles') ?></option>
                <?php foreach (['User', 'Admin'] as $r): ?>
                    <option value="<?= e($r) ?>"<?= $role === $r ? ' selected' : '' ?>><?= e(status_label($r)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit"><?= t('Apply') ?></button>
            <?php if ($q !== '' || $category !== '' || $role !== ''): ?><a class="btn btn-ghost" href="manage.php"><?= t('Clear') ?></a><?php endif; ?>
        </form>
    </div>

    <?php if ($users): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= t('User') ?></th>
                        <th scope="col"><?= t('Category') ?></th>
                        <th scope="col"><?= t('Role') ?></th>
                        <th scope="col" class="num"><?= t('Organised') ?></th>
                        <th scope="col" class="num"><?= t('Joined') ?></th>
                        <th scope="col"><?= t('Member since') ?></th>
                        <th scope="col" class="actions"><span class="sr-only"><?= t('Actions') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): $av = avatar_url($user['profile_pic']); ?>
                    <tr>
                        <td>
                            <div class="event-cell person-cell">
                                <?php if ($av): ?>
                                    <img class="avatar" src="<?= e($av) ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar" aria-hidden="true"><?= e(initials($user['name'])) ?></span>
                                <?php endif; ?>
                                <div>
                                    <div class="event-cell-title"><?= e($user['name']) ?><?= (int) $user['user_id'] === $me ? ' <span class="cell-muted">' . t('(you)') . '</span>' : '' ?></div>
                                    <div class="event-cell-meta"><?= e($user['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="cell-muted"><?= e(status_label($user['category'])) ?></td>
                        <td><?= status_badge($user['role']) ?></td>
                        <td class="num"><?= (int) $user['organised'] ?></td>
                        <td class="num"><?= (int) $user['joined'] ?></td>
                        <td class="cell-muted"><?= e(ldate('j M Y', $user['created_at'])) ?></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-icon" href="edit.php?id=<?= (int) $user['user_id'] ?>" title="<?= t('Edit user') ?>" aria-label="<?= t('Edit {name}', ['name' => $user['name']]) ?>"><i class="fa-solid fa-pen"></i></a>
                            <?php if ((int) $user['user_id'] !== $me): ?>
                                <form action="delete.php" method="POST" data-confirm="Delete <?= e($user['name']) ?>? Their registrations will be removed and their events will have no organiser.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $user['user_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-icon" title="<?= t('Delete user') ?>" aria-label="<?= t('Delete {name}', ['name' => $user['name']]) ?>"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($p, 'users') ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-user-slash"></i>
            <h3><?= t('No users match these filters') ?></h3>
            <a class="btn btn-secondary" href="manage.php"><?= t('Clear filters') ?></a>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
