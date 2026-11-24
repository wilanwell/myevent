<?php
/**
 * MyEvent Admin - Audit log of sensitive actions.
 */
require_once __DIR__ . '/../config.php';

require_admin();

$labels = [
    'user.delete'           => 'Deleted user',
    'user.update'           => 'Edited user',
    'user.role_change'      => 'Changed role',
    'event.create'          => 'Created event',
    'event.update'          => 'Edited event',
    'event.delete'          => 'Deleted event',
    'registration.approve'  => 'Approved registration',
    'registration.reject'   => 'Rejected registration',
    'registration.present'  => 'Checked in',
    'registration.checkin'  => 'Checked in (ticket)',
    'registration.absent'   => 'Undid check-in',
    'password.reset'        => 'Reset password',
];
$danger = ['user.delete', 'event.delete', 'user.role_change', 'password.reset'];

$q      = trim((string) ($_GET['q'] ?? ''));
$action = isset($labels[$_GET['action'] ?? '']) ? $_GET['action'] : '';

$where  = '1 = 1';
$types  = '';
$params = [];
if ($q !== '') {
    $where .= ' AND (actor_name LIKE ? OR details LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($action !== '') {
    $where .= ' AND action = ?';
    $types .= 's';
    $params[] = $action;
}

$total = (int) db_value($conn, "SELECT COUNT(*) FROM audit_log WHERE $where", $types, $params);
$p = paginate($total, 25);
$rows = db_all($conn,
    "SELECT actor_id, actor_name, action, target_type, target_id, details, ip_address, created_at
     FROM audit_log WHERE $where ORDER BY created_at DESC, audit_id DESC LIMIT ? OFFSET ?",
    $types . 'ii', array_merge($params, [$p['per_page'], $p['offset']]));

$page_title = 'Audit log';
$nav_active = 'admin_audit';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1>Audit log</h1>
        <p>Who changed what: deletions, role changes, approvals, check-ins and password resets.</p>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <form class="toolbar" method="GET" role="search">
            <label class="search-box">
                <span class="sr-only">Search the audit log</span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Person or details">
            </label>
            <label class="sr-only" for="a-action">Action</label>
            <select id="a-action" class="input" name="action" data-autosubmit>
                <option value="">All actions</option>
                <?php foreach ($labels as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $action === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit">Apply</button>
            <?php if ($q !== '' || $action !== ''): ?><a class="btn btn-ghost" href="audit.php">Clear</a><?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Who</th>
                        <th scope="col">Action</th>
                        <th scope="col">Details</th>
                        <th scope="col">IP address</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cell-muted"><time datetime="<?= e(date('c', strtotime($row['created_at']))) ?>"><?= e(date('j M Y, g:i A', strtotime($row['created_at']))) ?></time></td>
                        <td><?= e($row['actor_name'] ?? 'System') ?></td>
                        <td><span class="badge <?= in_array($row['action'], $danger, true) ? 'badge-danger' : 'badge-info' ?>"><?= e($labels[$row['action']] ?? $row['action']) ?></span></td>
                        <td><?= e($row['details']) ?><?php if ($row['target_type']): ?> <span class="cell-muted">(<?= e($row['target_type']) ?> #<?= (int) $row['target_id'] ?>)</span><?php endif; ?></td>
                        <td class="cell-muted"><?= e($row['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($p, 'entries') ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-clipboard-list"></i>
            <h3><?= $q !== '' || $action !== '' ? 'No entries match these filters' : 'Nothing recorded yet' ?></h3>
            <p>Sensitive actions are recorded here as they happen.</p>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
