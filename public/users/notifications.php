<?php
/**
 * MyEvent - Notifications for the current user.
 * Opening the page marks everything as read.
 */
require_once __DIR__ . '/../../config.php';

require_login();

$user_id = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    flash('success', __('All notifications cleared.'));
    redirect('users/notifications.php');
}

$total = (int) db_value($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ?', 'i', [$user_id]);
$p = paginate($total, 15);
$rows = db_all($conn,
    'SELECT message, is_read, created_at FROM notifications WHERE user_id = ?
     ORDER BY created_at DESC, notif_id DESC LIMIT ? OFFSET ?', 'iii', [$user_id, $p['per_page'], $p['offset']]);

// Mark as read after fetching, so unread items are still highlighted on this visit.
$mark = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
$mark->bind_param('i', $user_id);
$mark->execute();
$mark->close();

$page_title = __('Notifications');
$nav_active = 'notifications';
$nav_area   = 'auto';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header card-narrow">
    <div>
        <h1><?= t('Notifications') ?></h1>
        <p><?= t('Updates about your registrations and the events you organise.') ?></p>
    </div>
    <?php if ($total > 0): ?>
        <form method="POST" data-confirm="<?= t('Delete all notifications? This cannot be undone.') ?>">
            <?= csrf_field() ?>
            <button type="submit" name="clear_all" value="1" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> <?= t('Clear all') ?></button>
        </form>
    <?php endif; ?>
</div>

<section class="card card-narrow">
    <?php if ($rows): ?>
        <ul class="notice-list">
            <?php foreach ($rows as $row): ?>
                <li class="notice<?= (int) $row['is_read'] === 0 ? ' is-unread' : '' ?>">
                    <span class="notice-icon" aria-hidden="true"><i class="fa-regular fa-bell"></i></span>
                    <div class="notice-body">
                        <div><?= render_message($row['message']) ?></div>
                        <div class="notice-time">
                            <time datetime="<?= e(date('c', strtotime($row['created_at']))) ?>"><?= e(date('j M Y, g:i A', strtotime($row['created_at']))) ?></time>
                            <?php if ((int) $row['is_read'] === 0): ?><span class="badge badge-brand"><?= t('New') ?></span><?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= render_pagination($p, 'notifications') ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-regular fa-bell-slash"></i>
            <h3><?= t('You are all caught up') ?></h3>
            <p><?= t('We will let you know when an organiser reviews your registration or someone joins your event.') ?></p>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
