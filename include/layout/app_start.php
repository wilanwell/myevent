<?php
/**
 * Signed-in app layout (participant, organiser and admin areas): opening part.
 *
 * Set before including:
 *   $page_title  string  Shown in the <title> and the top bar.
 *   $nav_active  string  Key of the active sidebar link (see $nav below).
 *   $nav_area    string  'user' (default) or 'admin'.
 */
$page_title = $page_title ?? APP_NAME;
$nav_active = $nav_active ?? '';
$nav_area   = $nav_area ?? 'user';

$__uid    = current_user_id();
$__me     = db_all($conn, 'SELECT name, profile_pic FROM users WHERE user_id = ?', 'i', [$__uid])[0]
            ?? ['name' => $_SESSION['name'] ?? '', 'profile_pic' => null];
$__unread = (int) db_value($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$__uid]);
$__avatar = avatar_url($__me['profile_pic']);

$u = BASE_URL;
if ($nav_area === 'admin') {
    $nav = [
        __('Administration') => [
            'admin_overview' => [__('Overview'),     'fa-gauge-high',    $u . 'admin/index.php'],
            'admin_events'   => [__('All events'),   'fa-calendar-days', $u . 'admin/events/manage.php'],
            'admin_create'   => [__('Create event'), 'fa-circle-plus',   $u . 'admin/events/create.php'],
            'admin_reports'  => [__('Reports'),      'fa-chart-column',  $u . 'admin/reports.php'],
            'admin_users'    => [__('Users'),        'fa-users',         $u . 'admin/users/manage.php'],
            'admin_audit'    => [__('Audit log'),    'fa-clipboard-list', $u . 'admin/audit.php'],
            'admin_profile'  => [__('My profile'),   'fa-user-gear',     $u . 'admin/profile.php'],
        ],
        __('My account') => [
            'dashboard'      => [__('Participant view'), 'fa-user',      $u . 'users/dashboard.php'],
        ],
    ];
} else {
    $nav = [
        __('Participate') => [
            'dashboard'        => [__('Dashboard'),        'fa-house',             $u . 'users/dashboard.php'],
            'browse'           => [__('Browse events'),    'fa-magnifying-glass',  $u . 'browse_events.php'],
            'my_registrations' => [__('My registrations'), 'fa-ticket',            $u . 'users/my_registrations.php'],
            'notifications'    => [__('Notifications'),    'fa-bell',              $u . 'users/notifications.php', $__unread],
        ],
        __('Organise') => [
            'my_events'        => [__('My events'),        'fa-calendar-check',    $u . 'users/my_events.php'],
            'create_event'     => [__('Create event'),     'fa-circle-plus',       $u . 'users/create_event.php'],
            'participants'     => [__('Participants'),     'fa-users-gear',        $u . 'users/manage_participants.php'],
            'checkin'          => [__('Check in'),         'fa-qrcode',            $u . 'users/checkin.php'],
            'reports'          => [__('Reports'),          'fa-chart-column',      $u . 'users/reports.php'],
        ],
        __('Account') => [
            'profile'          => [__('My profile'),       'fa-user-pen',          $u . 'users/profile.php'],
        ],
    ];
    if (is_admin()) {
        $nav[__('Account')]['admin_overview'] = [__('Admin panel'), 'fa-shield-halved', $u . 'admin/index.php'];
    }
}
$__profileUrl = $nav_area === 'admin' ? $u . 'admin/profile.php' : $u . 'users/profile.php';
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <?php include ROOT_PATH . '/include/layout/theme_head.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= e($u) ?>assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&amp;family=Geist:wght@400..700&amp;display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e($u) ?>assets/css/app.css?v=17">
</head>
<body class="app">
<a class="skip-link" href="#main"><?= t('Skip to content') ?></a>

<aside class="sidebar" id="sidebar" aria-label="<?= t('Main navigation') ?>">
    <a class="sidebar-brand" href="<?= e($u) ?>index.php">
        <img src="<?= e($u) ?>assets/img/logo.png" alt="">
        <?= e(APP_NAME) ?>
        <?php if ($nav_area === 'admin'): ?><small><?= t('Admin') ?></small><?php endif; ?>
    </a>
    <nav class="sidebar-nav">
        <?php foreach ($nav as $__group => $__links): ?>
            <div class="sidebar-group">
                <div class="sidebar-group-title"><?= e($__group) ?></div>
                <?php foreach ($__links as $__key => $__l): $__on = $__key === $nav_active; ?>
                    <a class="nav-link<?= $__on ? ' is-active' : '' ?>" href="<?= e($__l[2]) ?>"<?= $__on ? ' aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= e($__l[1]) ?>" aria-hidden="true"></i><?= e($__l[0]) ?>
                        <?php if (!empty($__l[3])): ?><span class="count"><?= (int) $__l[3] ?><span class="sr-only"> <?= t('unread') ?></span></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a class="nav-link" href="<?= e($u) ?>index.php"><i class="fa-solid fa-globe" aria-hidden="true"></i><?= t('Public site') ?></a>
        <form action="<?= e($u) ?>auth/logout.php" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="nav-link is-danger"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><?= t('Log out') ?></button>
        </form>
    </div>
</aside>
<div class="scrim" data-nav-close></div>

<div class="app-shell">
    <?php include ROOT_PATH . '/include/partials/demo_band.php'; ?>
    <header class="topbar">
        <button type="button" class="icon-btn menu-toggle" data-nav-toggle aria-controls="sidebar" aria-expanded="false" aria-label="<?= t('Open menu') ?>">
            <i class="fa-solid fa-bars"></i>
        </button>
        <p class="topbar-title"><?= e($page_title) ?></p>
        <form class="topbar-search" action="<?= e($u) ?>browse_events.php" method="GET" role="search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <label class="sr-only" for="topbar-q"><?= t('Search events') ?></label>
            <input id="topbar-q" type="search" name="q" placeholder="<?= t('Search events, venues or topics') ?>">
        </form>
        <div class="topbar-actions">
            <?php include ROOT_PATH . '/include/partials/lang_switch.php'; ?>
            <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="<?= t('Switch to dark theme') ?>" title="<?= t('Switch theme') ?>"
                    data-label-dark="<?= t('Switch to dark theme') ?>" data-label-light="<?= t('Switch to light theme') ?>">
                <i class="fa-regular fa-moon" aria-hidden="true"></i><i class="fa-regular fa-sun" aria-hidden="true"></i>
            </button>
            <a class="icon-btn" href="<?= e($u) ?>users/notifications.php" aria-label="<?= $__unread ? t('Notifications ({n} unread)', ['n' => $__unread]) : t('Notifications') ?>">
                <i class="fa-regular fa-bell"></i>
                <?php if ($__unread): ?><span class="dot"><?= $__unread > 9 ? '9+' : $__unread ?></span><?php endif; ?>
            </a>
            <a class="user-chip" href="<?= e($__profileUrl) ?>">
                <?php if ($__avatar): ?>
                    <img class="avatar" src="<?= e($__avatar) ?>" alt="">
                <?php else: ?>
                    <span class="avatar" aria-hidden="true"><?= e(initials($__me['name'])) ?></span>
                <?php endif; ?>
                <span class="user-chip-name"><?= e($__me['name']) ?></span>
            </a>
        </div>
    </header>

    <main class="app-main" id="main">
        <?php include ROOT_PATH . '/include/flash.php'; ?>
