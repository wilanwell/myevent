<?php
/**
 * MyEvent - Public event page with registration.
 */
require_once __DIR__ . '/config.php';

$event_id = (int) ($_GET['id'] ?? 0);
if (!$event_id) {
    redirect('browse_events.php');
}

$event = db_all($conn,
    "SELECT e.*, c.categoryName AS category, u.name AS organizer_name,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken
     FROM events e
     LEFT JOIN users u ON u.user_id = e.created_by
     LEFT JOIN event_category c ON c.category_id = e.category_id
     WHERE e.event_id = ?", 'i', [$event_id])[0] ?? null;

if (!$event) {
    http_response_code(404);
    render_error_page('Event not found', 'This event does not exist or has been removed.');
}

// Registration availability (same rules as users/register_event.php)
$close_date = $event['reg_close_date'] ?: $event['event_date'];
$is_closed  = $close_date < date('Y-m-d');
$capacity   = (int) $event['max_participants'];
$taken      = (int) $event['taken'];
$is_full    = $capacity > 0 && $taken >= $capacity;
$is_owner   = is_logged_in() && (int) $event['created_by'] === current_user_id();
$status     = is_logged_in()
    ? db_value($conn, 'SELECT approval_status FROM registrations WHERE user_id = ? AND event_id = ?', 'ii', [current_user_id(), $event_id])
    : null;
$wait_pos   = is_logged_in() ? waitlist_position($conn, $event_id, current_user_id()) : null;
$wait_count = $is_full ? waitlist_count($conn, $event_id) : 0;
$is_past    = $event['event_date'] < date('Y-m-d');

$page_title = $event['event_name'];
$page_desc  = mb_substr(trim((string) $event['description']), 0, 155) ?: 'Event details and registration.';
$nav_current = 'browse';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="detail">
    <div class="detail-top">
        <?php include ROOT_PATH . '/include/flash.php'; ?>
        <a class="back-link" href="browse_events.php"><i class="fa-solid fa-arrow-left"></i> All events</a>
    </div>
    <header class="detail-head">
        <img class="detail-poster" src="<?= e(poster_url($event['poster_path'])) ?>" alt="Poster for <?= e($event['event_name']) ?>">

        <div class="badge-row">
            <span class="badge badge-brand no-dot"><?= e($event['category'] ?? 'General') ?></span>
            <span class="badge no-dot"><?= e($event['mode']) ?></span>
            <?php if ($event['visibility'] === 'Private'): ?><span class="badge badge-warning no-dot">Private event</span><?php endif; ?>
        </div>
        <h1 class="detail-title"><?= e($event['event_name']) ?></h1>
        <p class="detail-organiser">Organised by <?= e($event['organizer_name'] ?? APP_NAME) ?></p>
        <?php $rating = event_rating($conn, $event_id); if ($rating['count'] > 0): ?>
            <p class="rating-summary detail-rating">
                <?= rating_stars($rating['avg']) ?>
                <b><?= e(number_format($rating['avg'], 1)) ?></b>
                <span>from <?= $rating['count'] ?> <?= $rating['count'] === 1 ? 'rating' : 'ratings' ?></span>
            </p>
        <?php endif; ?>
    </header>

    <article class="detail-body">
        <?php if (trim((string) $event['description']) !== ''): ?>
            <section class="detail-section">
                <h2>About this event</h2>
                <p><?= nl2br(e($event['description'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if (trim((string) $event['remarks']) !== ''): ?>
            <section class="detail-section">
                <h2>Before you come</h2>
                <p><?= nl2br(e($event['remarks'])) ?></p>
            </section>
        <?php endif; ?>
    </article>

    <aside class="card detail-aside" aria-label="Event details and registration">
        <div class="card-body">
            <ul class="facts">
                <li><i class="fa-regular fa-calendar" aria-hidden="true"></i><div><b><?= e(date('l, j F Y', strtotime($event['event_date']))) ?></b><span><?= e(date('g:i A', strtotime($event['event_time']))) ?><?= !empty($event['end_time']) ? ' to ' . e(date('g:i A', strtotime($event['end_time']))) : '' ?></span></div></li>
                <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><div><b><?= e($event['venue']) ?></b><span><?= e($event['mode']) ?> event</span></div></li>
                <li><i class="fa-solid fa-tag" aria-hidden="true"></i><div><b><?= e(format_fee($event['fee'])) ?></b><span><?= (float) $event['fee'] > 0 ? 'Paid to the organiser' : 'No fee' ?></span></div></li>
                <li><i class="fa-regular fa-hourglass" aria-hidden="true"></i><div><b>Registration <?= $is_closed ? 'closed' : 'closes' ?> <?= e(date('j M Y', strtotime($close_date))) ?></b><span><?= $capacity > 0 ? max(0, $capacity - $taken) . ' of ' . $capacity . ' places left' : 'No limit on places' ?></span></div></li>
                <?php if (!empty($event['contact_no'])): ?>
                    <li><i class="fa-solid fa-phone" aria-hidden="true"></i><div><b><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $event['contact_no'])) ?>"><?= e($event['contact_no']) ?></a></b><span>Organiser contact</span></div></li>
                <?php endif; ?>
            </ul>

            <?php if ($capacity > 0): ?>
                <div class="capacity" role="img" aria-label="<?= $taken ?> of <?= $capacity ?> places taken"><span style="width: <?= min(100, round($taken / $capacity * 100)) ?>%"></span></div>
            <?php endif; ?>

            <?php if ($is_owner): ?>
                <div class="btn-stack">
                    <a class="btn btn-primary btn-block" href="users/manage_participants.php?event_id=<?= $event_id ?>"><i class="fa-solid fa-users"></i> Manage participants</a>
                    <a class="btn btn-secondary btn-block" href="users/edit_event.php?id=<?= $event_id ?>">Edit event</a>
                </div>
            <?php elseif ($status === 'Approved'): ?>
                <div class="status-box flash-success"><i class="fa-solid fa-circle-check"></i> You are registered and approved.</div>
                <a class="btn btn-primary btn-block btn-lg" href="users/generate_ticket.php?event_id=<?= $event_id ?>" target="_blank" rel="noopener"><i class="fa-solid fa-ticket"></i> View ticket</a>
            <?php elseif ($status === 'Pending'): ?>
                <div class="status-box flash-info"><i class="fa-regular fa-clock"></i> Registered. Waiting for the organiser to approve.</div>
            <?php elseif ($status === 'Rejected'): ?>
                <div class="status-box flash-error"><i class="fa-solid fa-circle-xmark"></i> Your registration was not approved.</div>
            <?php elseif ($is_closed): ?>
                <div class="status-box is-muted"><i class="fa-solid fa-lock"></i> Registration has closed.</div>
            <?php elseif ($wait_pos): ?>
                <div class="status-box flash-info"><i class="fa-solid fa-list-ol"></i> You are number <?= (int) $wait_pos ?> on the waitlist.</div>
                <p class="cell-muted no-margin">If a place opens up you will be registered automatically and notified.</p>
                <form action="users/register_event.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <button type="submit" name="action" value="leave_waitlist" class="btn btn-secondary btn-block">Leave the waitlist</button>
                </form>
            <?php elseif ($is_full && is_logged_in()): ?>
                <div class="status-box is-muted"><i class="fa-solid fa-users-slash"></i> This event is full<?= $wait_count ? ' and ' . $wait_count . ' ' . ($wait_count === 1 ? 'person is' : 'people are') . ' waiting' : '' ?>.</div>
                <form action="users/register_event.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <button type="submit" name="action" value="join_waitlist" class="btn btn-primary btn-block btn-lg"><i class="fa-solid fa-list-ol"></i> Join the waitlist</button>
                </form>
                <p class="cell-muted no-margin">You will be registered automatically, in order, if someone cancels.</p>
            <?php elseif ($is_full): ?>
                <div class="status-box is-muted"><i class="fa-solid fa-users-slash"></i> This event is full.</div>
                <a class="btn btn-primary btn-block btn-lg" href="auth/login.php">Log in to join the waitlist</a>
            <?php elseif (is_logged_in()): ?>
                <form action="users/register_event.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Register for this event</button>
                </form>
                <p class="cell-muted no-margin">The organiser reviews each registration. You will get a notification when it is approved.</p>
            <?php else: ?>
                <a class="btn btn-primary btn-block btn-lg" href="auth/login.php">Log in to register</a>
                <p class="cell-muted no-margin">New here? <a href="auth/register.php">Create a free account</a>.</p>
            <?php endif; ?>

            <?php if (!$is_past): ?>
                <div class="calendar-links">
                    <span class="cell-muted">Add to calendar</span>
                    <a class="btn btn-ghost btn-sm" href="<?= e(google_calendar_url($event)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-google" aria-hidden="true"></i> Google</a>
                    <a class="btn btn-ghost btn-sm" href="event_calendar.php?id=<?= $event_id ?>"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i> Apple / Outlook</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
