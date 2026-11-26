<?php
/**
 * MyEvent Admin - Overview
 */
require_once __DIR__ . '/../config.php';

require_admin();

$stats = [
    'users'    => (int) db_value($conn, 'SELECT COUNT(*) FROM users'),
    'students' => (int) db_value($conn, "SELECT COUNT(*) FROM users WHERE category = 'Student'"),
    'staff'    => (int) db_value($conn, "SELECT COUNT(*) FROM users WHERE category = 'Staff'"),
    'upcoming' => (int) db_value($conn, 'SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()'),
    'events'   => (int) db_value($conn, 'SELECT COUNT(*) FROM events'),
    'regs'     => (int) db_value($conn, 'SELECT COUNT(*) FROM registrations'),
    'pending'  => (int) db_value($conn, "SELECT COUNT(*) FROM registrations WHERE approval_status = 'Pending'"),
];

$upcoming = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.venue, e.max_participants, u.name AS organiser,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status = 'Approved') AS approved,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status = 'Pending') AS pending
     FROM events e LEFT JOIN users u ON u.user_id = e.created_by
     WHERE e.event_date >= CURDATE()
     ORDER BY e.event_date ASC LIMIT 6");

$recent = db_all($conn,
    'SELECT r.registration_date, r.approval_status, u.name, e.event_id, e.event_name
     FROM registrations r JOIN users u ON u.user_id = r.user_id JOIN events e ON e.event_id = r.event_id
     ORDER BY r.registration_date DESC, r.reg_id DESC LIMIT 6');

$page_title = 'Overview';
$nav_active = 'admin_overview';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1>Overview</h1>
        <p>Activity across every event and account on <?= e(APP_NAME) ?>.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
        <a class="btn btn-secondary" href="users/manage.php"><i class="fa-solid fa-users"></i> Users</a>
        <a class="btn btn-primary" href="events/create.php"><i class="fa-solid fa-plus"></i> Create event</a>
    </div>
</div>

<div class="stats">
    <a class="stat" href="users/manage.php">
        <span class="stat-icon"><i class="fa-solid fa-users"></i></span>
        <span><span class="stat-value"><?= $stats['users'] ?></span><br><span class="stat-label"><?= $stats['students'] ?> students, <?= $stats['staff'] ?> staff</span></span>
    </a>
    <a class="stat" href="events/manage.php">
        <span class="stat-icon is-sky"><i class="fa-solid fa-calendar-days"></i></span>
        <span><span class="stat-value"><?= $stats['upcoming'] ?></span><br><span class="stat-label">Upcoming of <?= $stats['events'] ?> events</span></span>
    </a>
    <div class="stat">
        <span class="stat-icon is-green"><i class="fa-solid fa-ticket"></i></span>
        <span><span class="stat-value"><?= $stats['regs'] ?></span><br><span class="stat-label">Registrations</span></span>
    </div>
    <div class="stat">
        <span class="stat-icon is-amber"><i class="fa-regular fa-clock"></i></span>
        <span><span class="stat-value"><?= $stats['pending'] ?></span><br><span class="stat-label">Waiting for organiser review</span></span>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Coming up</h2>
            <a class="btn btn-ghost btn-sm" href="events/manage.php">All events</a>
        </div>
        <?php if ($upcoming): ?>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    <?php foreach ($upcoming as $row): ?>
                        <tr>
                            <td>
                                <div class="event-cell">
                                    <?= datestub($row['event_date']) ?>
                                    <div>
                                        <a class="event-cell-title" href="organizer/manage_participants.php?event_id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                        <div class="event-cell-meta"><?= e($row['organiser'] ?? 'No organiser') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="num">
                                <?= (int) $row['approved'] ?><?= (int) $row['max_participants'] > 0 ? ' / ' . (int) $row['max_participants'] : '' ?>
                                <?php if ((int) $row['pending'] > 0): ?><span class="badge badge-warning cell-sub"><?= (int) $row['pending'] ?> pending</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fa-regular fa-calendar"></i><h3>No upcoming events</h3><a class="btn btn-primary" href="events/create.php">Create event</a></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Latest registrations</h2>
        </div>
        <?php if ($recent): ?>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td>
                                <div class="event-cell person-cell">
                                    <span class="avatar" aria-hidden="true"><?= e(initials($row['name'])) ?></span>
                                    <div>
                                        <div class="event-cell-title"><?= e($row['name']) ?></div>
                                        <a class="event-cell-meta" href="organizer/manage_participants.php?event_id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                    </div>
                                </div>
                            </td>
                            <td class="actions"><?= status_badge($row['approval_status']) ?><div class="cell-muted"><?= e(date('j M', strtotime($row['registration_date']))) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fa-solid fa-ticket"></i><h3>No registrations yet</h3></div>
        <?php endif; ?>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
