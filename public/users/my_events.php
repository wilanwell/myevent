<?php
/**
 * MyEvent - Events organised by the current user.
 */
require_once __DIR__ . '/../../config.php';

require_login();

$user_id = current_user_id();

// Delete an event the user owns (POST, CSRF-checked in config.php). Registrations cascade.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $target_id = (int) ($_POST['event_id'] ?? 0);
    $owned = db_all($conn, 'SELECT event_name, poster_path FROM events WHERE event_id = ? AND created_by = ?', 'ii', [$target_id, $user_id])[0] ?? null;

    if ($owned) {
        $told = notify_event_cancelled($conn, $target_id);
        $stmt = $conn->prepare('DELETE FROM events WHERE event_id = ? AND created_by = ?');
        $stmt->bind_param('ii', $target_id, $user_id);
        $stmt->execute();
        $stmt->close();
        delete_upload($owned['poster_path'], POSTER_DIR);
        audit($conn, 'event.delete', 'event', $target_id, $owned['event_name']);
        flash('success', __('Event deleted.') . ($told ? ' ' . _n('We let {n} registered person know it is cancelled.', 'We let {n} registered people know it is cancelled.', $told) : ''));
    } else {
        flash('error', __('You can only delete events you organise.'));
    }
    redirect('users/my_events.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$where = 'e.created_by = ?';
$types = 'i';
$params = [$user_id];
if ($q !== '') {
    $where .= ' AND (e.event_name LIKE ? OR e.venue LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}

$total = (int) db_value($conn, "SELECT COUNT(*) FROM events e WHERE $where", $types, $params);
$p = paginate($total, 10);

$rows = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.venue, e.visibility, e.max_participants, e.poster_path,
            c.categoryName,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status = 'Pending') AS pending
     FROM events e
     LEFT JOIN event_category c ON c.category_id = e.category_id
     WHERE $where
     ORDER BY e.event_date DESC
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$p['per_page'], $p['offset']]));

$page_title = __('My events');
$nav_active = 'my_events';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= t('My events') ?></h1>
        <p><?= t('Events you organise. Review registrations, edit details or remove an event.') ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="create_event.php"><i class="fa-solid fa-plus"></i> <?= t('Create event') ?></a>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <form class="toolbar" method="GET" role="search">
            <label class="search-box">
                <span class="sr-only"><?= t('Search your events') ?></span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Search by name or venue') ?>">
            </label>
            <button class="btn btn-secondary" type="submit"><?= t('Search') ?></button>
            <?php if ($q !== ''): ?><a class="btn btn-ghost" href="my_events.php"><?= t('Clear') ?></a><?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= t('Event') ?></th>
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
                        <td class="cell-muted"><?= e($row['categoryName'] ?? __('Uncategorised')) ?></td>
                        <td><?= status_badge($row['visibility']) ?></td>
                        <td class="num">
                            <?= (int) $row['taken'] ?> / <?= (int) $row['max_participants'] > 0 ? (int) $row['max_participants'] : '&infin;' ?>
                            <?php if ((int) $row['pending'] > 0): ?><span class="badge badge-warning cell-sub"><?= t('{n} to review', ['n' => (int) $row['pending']]) ?></span><?php endif; ?>
                        </td>
                        <td class="actions">
                            <a class="btn btn-secondary btn-sm" href="manage_participants.php?event_id=<?= (int) $row['event_id'] ?>"><i class="fa-solid fa-users"></i> <?= t('Participants') ?></a>
                            <a class="btn btn-ghost btn-icon" href="edit_event.php?id=<?= (int) $row['event_id'] ?>" title="<?= t('Edit event') ?>" aria-label="<?= t('Edit {name}', ['name' => $row['event_name']]) ?>"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" data-confirm="<?= t('Delete this event? All of its registrations will be removed too.') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $row['event_id'] ?>">
                                <button type="submit" name="delete_event" value="1" class="btn btn-danger btn-icon" title="<?= t('Delete event') ?>" aria-label="<?= t('Delete {name}', ['name' => $row['event_name']]) ?>"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($p, 'events') ?>
    <?php elseif ($q !== ''): ?>
        <div class="empty-state">
            <i class="fa-solid fa-magnifying-glass"></i>
            <h3>No events match "<?= e($q) ?>"</h3>
            <p><?= t('Try a different name or venue.') ?></p>
            <a class="btn btn-secondary" href="my_events.php"><?= t('Clear search') ?></a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-bullhorn"></i>
            <h3><?= t('You have not created any events') ?></h3>
            <p><?= t('Publish your first event and start taking registrations.') ?></p>
            <a class="btn btn-primary" href="create_event.php"><?= t('Create event') ?></a>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
