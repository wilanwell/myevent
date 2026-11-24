<?php
/**
 * MyEvent - Home page
 */
require_once __DIR__ . '/config.php';

$card_cols = "e.event_id, e.event_name, e.event_date, e.event_time, e.venue, e.mode, e.fee, e.poster_path,
              e.max_participants, e.reg_close_date, c.categoryName,
              (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken";

// Private events are unlisted: reachable by direct link only.
$upcoming = db_all($conn,
    "SELECT $card_cols FROM events e LEFT JOIN event_category c ON c.category_id = e.category_id
     WHERE e.visibility = 'Public' AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC, e.event_time ASC LIMIT 7");

$next = array_shift($upcoming);
$open_count = (int) db_value($conn, "SELECT COUNT(*) FROM events WHERE visibility = 'Public' AND COALESCE(reg_close_date, event_date) >= CURDATE()");
$categories = db_all($conn, 'SELECT category_id, categoryName FROM event_category ORDER BY categoryName');

$page_title  = 'Campus events';
$nav_current = 'home';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<section class="hero">
    <div class="hero-inner">
        <div>
            <?php include ROOT_PATH . '/include/flash.php'; ?>
            <h1>Find something worth showing up for.</h1>
            <p>Workshops, talks, competitions and festivals happening on campus. Register in a minute and keep your ticket on your phone.</p>
            <form class="hero-search" action="browse_events.php" method="GET" role="search">
                <label class="search-box">
                    <span class="sr-only">Search events</span>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="input" type="search" name="q" placeholder="Search by event name or venue">
                </label>
                <button class="btn btn-primary" type="submit">Search</button>
            </form>
            <p class="hero-meta"><?= $open_count ?> <?= $open_count === 1 ? 'event is' : 'events are' ?> open for registration. <a href="browse_events.php">See all events</a></p>
        </div>

        <?php if ($next): $ts = strtotime($next['event_date']); ?>
          <div class="hero-board">
            <a class="ticket" href="event_detail.php?id=<?= (int) $next['event_id'] ?>" aria-label="Next up: <?= e($next['event_name']) ?>">
                <div class="ticket-stub" aria-hidden="true">
                    <span class="ticket-weekday"><?= date('l', $ts) ?></span>
                    <span class="ticket-day"><?= date('j', $ts) ?></span>
                    <span class="ticket-month"><?= date('M Y', $ts) ?></span>
                </div>
                <div class="ticket-body">
                    <span class="ticket-label">Next up</span>
                    <h2 class="ticket-title"><?= e($next['event_name']) ?></h2>
                    <ul class="ticket-facts">
                        <li><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e(date('g:i A', strtotime($next['event_time']))) ?></li>
                        <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e($next['venue']) ?></li>
                        <li><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= e(format_fee($next['fee'])) ?>, <?= e($next['categoryName'] ?? 'General') ?></li>
                    </ul>
                </div>
                <img class="ticket-poster" src="<?= e(poster_url($next['poster_path'])) ?>" alt="">
            </a>
          </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Coming up</h2>
            <p>The next events open to everyone.</p>
        </div>
        <a class="btn btn-secondary" href="browse_events.php">Browse all events</a>
    </div>

    <nav class="chips" aria-label="Categories">
        <?php foreach ($categories as $c): ?>
            <a class="chip" href="browse_events.php?category=<?= (int) $c['category_id'] ?>"><?= e($c['categoryName']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($upcoming): ?>
        <div class="event-grid">
            <?php foreach ($upcoming as $ev) { include ROOT_PATH . '/include/partials/event_card.php'; } ?>
        </div>
    <?php elseif (!$next): ?>
        <div class="public-card empty-state">
            <i class="fa-regular fa-calendar"></i>
            <h3>No upcoming events right now</h3>
            <p>New events are added all the time. Organising something? Publish it here.</p>
            <a class="btn btn-primary" href="<?= is_logged_in() ? 'users/create_event.php' : 'auth/register.php' ?>">Create an event</a>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
