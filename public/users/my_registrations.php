<?php
/**
 * MyEvent - Events the current user has registered for.
 */
require_once __DIR__ . '/../../config.php';

require_login();

$user_id = current_user_id();

// Cancel a registration (POST, CSRF-checked in config.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_registration'])) {
    $event_id = (int) ($_POST['event_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM registrations WHERE event_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $event_id, $user_id);
    $stmt->execute();
    $cancelled = $stmt->affected_rows > 0;
    $stmt->close();
    if ($cancelled) {
        promote_waitlist($conn, $event_id);
    }
    flash($cancelled ? 'success' : 'error', $cancelled ? __('Your registration has been cancelled.') : __('That registration was not found.'));
    redirect('users/my_registrations.php');
}

$show = ($_GET['show'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$dateFilter = $show === 'past' ? 'e.event_date < CURDATE()' : 'e.event_date >= CURDATE()';
$order = $show === 'past' ? 'DESC' : 'ASC';

$total = (int) db_value($conn, "SELECT COUNT(*) FROM registrations r JOIN events e ON e.event_id = r.event_id WHERE r.user_id = ? AND $dateFilter", 'i', [$user_id]);
$p = paginate($total, 10);

$rows = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.venue, e.mode,
            r.approval_status, r.attendance_status, r.registration_date, f.rating AS my_rating
     FROM registrations r JOIN events e ON e.event_id = r.event_id
     LEFT JOIN feedback f ON f.event_id = r.event_id AND f.user_id = r.user_id
     WHERE r.user_id = ? AND $dateFilter
     ORDER BY e.event_date $order
     LIMIT ? OFFSET ?", 'iii', [$user_id, $p['per_page'], $p['offset']]);

$waiting = $show === 'upcoming' ? db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.venue,
            (SELECT COUNT(*) FROM waitlist w2 WHERE w2.event_id = w.event_id
               AND (w2.created_at < w.created_at OR (w2.created_at = w.created_at AND w2.waitlist_id <= w.waitlist_id))) AS position
     FROM waitlist w JOIN events e ON e.event_id = w.event_id
     WHERE w.user_id = ? AND e.event_date >= CURDATE()
     ORDER BY e.event_date", 'i', [$user_id]) : [];

$page_title = __('My registrations');
$nav_active = 'my_registrations';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= t('My registrations') ?></h1>
        <p><?= t('Track approval status, download tickets and cancel spots you no longer need.') ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-<?= $show === 'upcoming' ? 'primary' : 'secondary' ?>" href="?show=upcoming"><?= t('Upcoming') ?></a>
        <a class="btn btn-<?= $show === 'past' ? 'primary' : 'secondary' ?>" href="?show=past"><?= t('Past') ?></a>
    </div>
</div>

<?php if ($waiting): ?>
<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= t('On the waitlist') ?></h2>
            <p class="card-subtitle"><?= t('You will be registered automatically, in order, when a place opens up.') ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
            <?php foreach ($waiting as $row): ?>
                <tr>
                    <td>
                        <div class="event-cell">
                            <?= datestub($row['event_date']) ?>
                            <div>
                                <a class="event-cell-title" href="<?= e(BASE_URL) ?>event_detail.php?id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                <div class="event-cell-meta"><?= e(ldate('g:i A', $row['event_time'])) ?>, <?= e($row['venue']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-info"><?= t('Number {n} in line', ['n' => (int) $row['position']]) ?></span></td>
                    <td class="actions">
                        <form action="register_event.php" method="POST" data-confirm="<?= t('Leave the waitlist for this event?') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int) $row['event_id'] ?>">
                            <input type="hidden" name="return" value="registrations">
                            <button type="submit" name="action" value="leave_waitlist" class="btn btn-secondary btn-sm"><?= t('Leave') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= t('Event') ?></th>
                        <th scope="col"><?= t('Mode') ?></th>
                        <th scope="col"><?= t('Status') ?></th>
                        <?php if ($show === 'past'): ?><th scope="col"><?= t('Attendance') ?></th><?php endif; ?>
                        <th scope="col" class="actions"><span class="sr-only"><?= t('Actions') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="event-cell">
                                <?= datestub($row['event_date']) ?>
                                <div>
                                    <a class="event-cell-title" href="<?= e(BASE_URL) ?>event_detail.php?id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                    <div class="event-cell-meta"><?= e(ldate('g:i A', $row['event_time'])) ?>, <?= e($row['venue']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="cell-muted"><?= e(status_label($row['mode'])) ?></td>
                        <td><?= status_badge($row['approval_status']) ?></td>
                        <?php if ($show === 'past'): ?><td><?= status_badge($row['attendance_status']) ?></td><?php endif; ?>
                        <td class="actions">
                            <?php if ($show === 'past' && $row['approval_status'] === 'Approved'): ?>
                                <?php if ($row['my_rating']): ?>
                                    <?= rating_stars((float) $row['my_rating']) ?>
                                    <a class="btn btn-ghost btn-sm" href="feedback.php?event_id=<?= (int) $row['event_id'] ?>"><?= t('Edit') ?></a>
                                <?php else: ?>
                                    <a class="btn btn-primary btn-sm" href="feedback.php?event_id=<?= (int) $row['event_id'] ?>"><i class="fa-regular fa-star"></i> <?= t('Rate event') ?></a>
                                <?php endif; ?>
                            <?php elseif ($row['approval_status'] === 'Approved'): ?>
                                <a class="btn btn-secondary btn-sm" href="generate_ticket.php?event_id=<?= (int) $row['event_id'] ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-ticket"></i> <?= t('Ticket') ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($show === 'upcoming'): ?>
                                <form method="POST" data-confirm="<?= t('Cancel your registration for this event? Your spot will be released.') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= (int) $row['event_id'] ?>">
                                    <button type="submit" name="cancel_registration" value="1" class="btn btn-danger btn-sm"><?= t('Cancel') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($p, 'registrations') ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-ticket"></i>
            <?php if ($show === 'past'): ?>
                <h3><?= t('No past events yet') ?></h3>
                <p><?= t('Events you attended will be listed here.') ?></p>
            <?php else: ?>
                <h3><?= t('You have no upcoming registrations') ?></h3>
                <p><?= t('Browse what is happening on campus and save your spot.') ?></p>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>browse_events.php"><?= t('Browse events') ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
