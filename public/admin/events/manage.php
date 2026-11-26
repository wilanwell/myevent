<?php
/**
 * MyEvent Admin - All events: search, filter and manage.
 */
require_once __DIR__ . '/../../../config.php';

require_admin();

$q        = trim((string) ($_GET['q'] ?? ''));
$category = (int) ($_GET['category'] ?? 0);
$when     = in_array($_GET['when'] ?? '', ['upcoming', 'past'], true) ? $_GET['when'] : '';

$where  = '1 = 1';
$types  = '';
$params = [];
if ($q !== '') {
    $where .= ' AND (e.event_name LIKE ? OR e.venue LIKE ? OR u.name LIKE ?)';
    $types .= 'sss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($category > 0) {
    $where .= ' AND e.category_id = ?';
    $types .= 'i';
    $params[] = $category;
}
if ($when === 'upcoming') {
    $where .= ' AND e.event_date >= CURDATE()';
} elseif ($when === 'past') {
    $where .= ' AND e.event_date < CURDATE()';
}

$from  = 'FROM events e LEFT JOIN users u ON u.user_id = e.created_by LEFT JOIN event_category c ON c.category_id = e.category_id';
$total = (int) db_value($conn, "SELECT COUNT(*) $from WHERE $where", $types, $params);
$p     = paginate($total, 12);
$rows  = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.venue, e.visibility, e.max_participants, e.poster_path,
            u.name AS organiser, c.categoryName,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken
     $from WHERE $where
     ORDER BY e.event_date DESC
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$p['per_page'], $p['offset']]));

$categories = db_all($conn, 'SELECT category_id, categoryName FROM event_category ORDER BY categoryName');

$page_title = __('All events');
$nav_active = 'admin_events';
$nav_area   = 'admin';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= t('All events') ?></h1>
        <p><?= t('Every event on {app}, whoever organises it.', ['app' => APP_NAME]) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> <?= t('Create event') ?></a>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <form class="toolbar" method="GET" role="search">
            <label class="search-box">
                <span class="sr-only"><?= t('Search events') ?></span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Event, venue or organiser') ?>">
            </label>
            <label class="sr-only" for="ev-cat"><?= t('Category') ?></label>
            <select id="ev-cat" class="input" name="category" data-autosubmit>
                <option value=""><?= t('All categories') ?></option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>"<?= $category === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['categoryName']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="ev-when"><?= t('Date') ?></label>
            <select id="ev-when" class="input" name="when" data-autosubmit>
                <option value=""><?= t('Any date') ?></option>
                <option value="upcoming"<?= $when === 'upcoming' ? ' selected' : '' ?>><?= t('Upcoming') ?></option>
                <option value="past"<?= $when === 'past' ? ' selected' : '' ?>><?= t('Past') ?></option>
            </select>
            <button class="btn btn-secondary" type="submit"><?= t('Apply') ?></button>
            <?php if ($q !== '' || $category || $when !== ''): ?><a class="btn btn-ghost" href="manage.php"><?= t('Clear') ?></a><?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= t('Event') ?></th>
                        <th scope="col"><?= t('Organiser') ?></th>
                        <th scope="col"><?= t('Category') ?></th>
                        <th scope="col"><?= t('Visibility') ?></th>
                        <th scope="col" class="num"><?= t('Registered') ?></th>
                        <th scope="col" class="actions"><span class="sr-only"><?= t('Actions') ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="event-cell">
                                <?= datestub($row['event_date']) ?>
                                <img class="thumb" src="<?= e(poster_url($row['poster_path'])) ?>" alt="" loading="lazy">
                                <div>
                                    <a class="event-cell-title" href="<?= e(BASE_URL) ?>event_detail.php?id=<?= (int) $row['event_id'] ?>"><?= e($row['event_name']) ?></a>
                                    <div class="event-cell-meta"><?= e($row['venue']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="cell-muted"><?= e($row['organiser'] ?? __('No organiser')) ?></td>
                        <td class="cell-muted"><?= e($row['categoryName'] ?? __('Uncategorised')) ?></td>
                        <td><?= status_badge($row['visibility']) ?></td>
                        <td class="num"><?= (int) $row['taken'] ?> / <?= (int) $row['max_participants'] > 0 ? (int) $row['max_participants'] : '&infin;' ?></td>
                        <td class="actions">
                            <a class="btn btn-secondary btn-sm" href="../organizer/manage_participants.php?event_id=<?= (int) $row['event_id'] ?>"><i class="fa-solid fa-users"></i> <?= t('Participants') ?></a>
                            <a class="btn btn-ghost btn-icon" href="edit.php?id=<?= (int) $row['event_id'] ?>" title="<?= t('Edit event') ?>" aria-label="<?= t('Edit {name}', ['name' => $row['event_name']]) ?>"><i class="fa-solid fa-pen"></i></a>
                            <form action="delete.php" method="POST" data-confirm="<?= t('Delete this event and all of its registrations?') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['event_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-icon" title="<?= t('Delete event') ?>" aria-label="<?= t('Delete {name}', ['name' => $row['event_name']]) ?>"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($p, 'events') ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar-xmark"></i>
            <h3><?= $total === 0 && $q === '' && !$category && $when === '' ? t('No events yet') : t('No events match these filters') ?></h3>
            <a class="btn btn-secondary" href="manage.php"><?= t('Clear filters') ?></a>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
