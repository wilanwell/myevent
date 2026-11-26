<?php
/**
 * MyEvent Admin - Audit log of sensitive actions.
 */
require_once __DIR__ . '/../../config.php';

require_admin();

$labels = [
    'user.delete'           => __('Deleted user'),
    'user.update'           => __('Edited user'),
    'user.role_change'      => __('Changed role'),
    'event.create'          => __('Created event'),
    'event.update'          => __('Edited event'),
    'event.delete'          => __('Deleted event'),
    'registration.approve'  => __('Approved registration'),
    'registration.reject'   => __('Rejected registration'),
    'registration.present'  => __('Checked in'),
    'registration.checkin'  => __('Checked in (ticket)'),
    'registration.absent'   => __('Undid check-in'),
    'password.reset'        => __('Reset password'),
    'waitlist.promote'      => __('Moved up from waitlist'),
    'report.export'         => __('Exported report'),
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

$page_title = __('Audit log');
$nav_active = 'admin_audit';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= t('Audit log') ?></h1>
        <p><?= t('Who changed what: deletions, role changes, approvals, check-ins and password resets.') ?></p>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <form class="toolbar" method="GET" role="search">
            <label class="search-box">
                <span class="sr-only"><?= t('Search the audit log') ?></span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Person or details') ?>">
            </label>
            <label class="sr-only" for="a-action"><?= t('Action') ?></label>
            <select id="a-action" class="input" name="action" data-autosubmit>
                <option value=""><?= t('All actions') ?></option>
                <?php foreach ($labels as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $action === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit"><?= t('Apply') ?></button>
            <?php if ($q !== '' || $action !== ''): ?><a class="btn btn-ghost" href="audit.php"><?= t('Clear') ?></a><?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= t('When') ?></th>
                        <th scope="col"><?= t('Who') ?></th>
                        <th scope="col"><?= t('Action') ?></th>
                        <th scope="col"><?= t('Details') ?></th>
                        <th scope="col"><?= t('IP address') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cell-muted"><time datetime="<?= e(date('c', strtotime($row['created_at']))) ?>"><?= e(ldate('j M Y, g:i A', $row['created_at'])) ?></time></td>
                        <td><?= e($row['actor_name'] ?? __('System')) ?></td>
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
            <h3><?= $q !== '' || $action !== '' ? t('No entries match these filters') : t('Nothing recorded yet') ?></h3>
            <p><?= t('Sensitive actions are recorded here as they happen.') ?></p>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
