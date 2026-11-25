<?php
/**
 * MyEvent - Participant dashboard
 */
require_once __DIR__ . '/../config.php';

require_login();

$user_id = current_user_id();

$my_registrations  = (int) db_value($conn, 'SELECT COUNT(*) FROM registrations WHERE user_id = ?', 'i', [$user_id]);
$my_pending        = (int) db_value($conn, "SELECT COUNT(*) FROM registrations WHERE user_id = ? AND approval_status = 'Pending'", 'i', [$user_id]);
$my_created_events = (int) db_value($conn, 'SELECT COUNT(*) FROM events WHERE created_by = ?', 'i', [$user_id]);
$open_events       = (int) db_value($conn, "SELECT COUNT(*) FROM events WHERE visibility = 'Public' AND COALESCE(reg_close_date, event_date) >= CURDATE()");

$upcoming = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.venue, r.approval_status
     FROM registrations r JOIN events e ON e.event_id = r.event_id
     WHERE r.user_id = ? AND e.event_date >= CURDATE() AND r.approval_status <> 'Rejected'
     ORDER BY e.event_date ASC LIMIT 5", 'i', [$user_id]);

$to_rate = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date
     FROM registrations r JOIN events e ON e.event_id = r.event_id
     LEFT JOIN feedback f ON f.event_id = r.event_id AND f.user_id = r.user_id
     WHERE r.user_id = ? AND r.approval_status = 'Approved' AND f.id IS NULL
       AND TIMESTAMP(e.event_date, e.event_time) <= NOW() AND e.event_date >= CURDATE() - INTERVAL 30 DAY
     ORDER BY e.event_date DESC LIMIT 3", 'i', [$user_id]);

$organising = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status = 'Pending') AS pending
     FROM events e WHERE e.created_by = ? AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC LIMIT 5", 'i', [$user_id]);

$page_title = 'Dashboard';
$nav_active = 'dashboard';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1>Welcome back, <?= e(strtok($_SESSION['name'] ?? '', ' ')) ?></h1>
        <p>Here is what is coming up for you.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>browse_events.php"><i class="fa-solid fa-magnifying-glass"></i> Browse events</a>
        <a class="btn btn-primary" href="create_event.php"><i class="fa-solid fa-plus"></i> Create event</a>
    </div>
</div>

<div class="stats">
    <a class="stat" href="my_registrations.php">
        <span class="stat-icon"><i class="fa-solid fa-ticket"></i></span>
        <span><span class="stat-value"><?= $my_registrations ?></span><br><span class="stat-label">Registrations</span></span>
    </a>
    <a class="stat" href="my_registrations.php">
        <span class="stat-icon is-amber"><i class="fa-regular fa-clock"></i></span>
        <span><span class="stat-value"><?= $my_pending ?></span><br><span class="stat-label">Awaiting approval</span></span>
    </a>
    <a class="stat" href="my_events.php">
        <span class="stat-icon is-green"><i class="fa-solid fa-calendar-check"></i></span>
        <span><span class="stat-value"><?= $my_created_events ?></span><br><span class="stat-label">Events I organise</span></span>
    </a>
    <a class="stat" href="<?= e(BASE_URL) ?>browse_events.php">
        <span class="stat-icon is-sky"><i class="fa-solid fa-door-open"></i></span>
        <span><span class="stat-value"><?= $open_events ?></span><br><span class="stat-label">Open for registration</span></span>
    </a>
</div>

<?php if ($to_rate): ?>
<section class="card rate-prompt">
    <div class="card-header">
        <div>
            <h2 class="card-title">How was it?</h2>
            <p class="card-subtitle">Rate the events you attended recently. It takes ten seconds and helps organisers improve.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
            <?php foreach ($to_rate as $row): ?>
                <tr>
                    <td><div class="event-cell"><?= datestub($row['event_date']) ?><span class="event-cell-title"><?= e($row['event_name']) ?></span></div></td>
                    <td class="actions"><a class="btn btn-primary btn-sm" href="feedback.php?event_id=<?= (int) $row['event_id'] ?>"><i class="fa-regular fa-star"></i> Rate event</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">My upcoming events</h2>
            <a class="btn btn-ghost btn-sm" href="my_registrations.php">View all</a>
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
                                        <a class="event-cell-title" href="<?= e(BASE_URL) ?>event_detail.php?id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                        <div class="event-cell-meta"><?= e(date('g:i A', strtotime($row['event_time']))) ?>, <?= e($row['venue']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="actions"><?= status_badge($row['approval_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-regular fa-calendar"></i>
                <h3>No upcoming events</h3>
                <p>Find a workshop, talk or competition and register in a few clicks.</p>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>browse_events.php">Browse events</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Events I organise</h2>
            <a class="btn btn-ghost btn-sm" href="my_events.php">Manage</a>
        </div>
        <?php if ($organising): ?>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    <?php foreach ($organising as $row): ?>
                        <tr>
                            <td>
                                <div class="event-cell">
                                    <?= datestub($row['event_date']) ?>
                                    <a class="event-cell-title" href="manage_participants.php?event_id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                </div>
                            </td>
                            <td class="actions">
                                <?php if ((int) $row['pending'] > 0): ?>
                                    <a class="badge badge-warning" href="manage_participants.php?event_id=<?= (int) $row['event_id'] ?>"><?= (int) $row['pending'] ?> to review</a>
                                <?php else: ?>
                                    <span class="cell-muted">Up to date</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-bullhorn"></i>
                <h3>You are not organising anything yet</h3>
                <p>Create an event to start taking registrations.</p>
                <a class="btn btn-primary" href="create_event.php">Create event</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
