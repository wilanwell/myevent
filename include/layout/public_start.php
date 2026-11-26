<?php
/**
 * Public layout (home, browse, event detail, sign-in): opening part.
 *
 * Set before including:
 *   $page_title   string  <title> text.
 *   $nav_current  string  'home' | 'browse' | 'login' | 'register'
 *   $page_desc    string  Optional meta description.
 */
$page_title  = $page_title ?? APP_NAME;
$nav_current = $nav_current ?? '';
$page_desc   = $page_desc ?? __('Find, join and organise campus events.');
$u = BASE_URL;

$__unread = 0;
$__me = null;
if (is_logged_in()) {
    $__me = db_all($conn, 'SELECT name, profile_pic FROM users WHERE user_id = ?', 'i', [current_user_id()])[0] ?? null;
    $__unread = (int) db_value($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [current_user_id()]);
}
$__cur = static fn(string $key) => $nav_current === $key ? ' aria-current="page"' : '';
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <?php include ROOT_PATH . '/include/layout/theme_head.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($page_desc) ?>">
    <title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= e($u) ?>assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&amp;family=Geist:wght@400..700&amp;display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e($u) ?>assets/css/app.css?v=17">
    <link rel="stylesheet" href="<?= e($u) ?>assets/css/public.css?v=17">
</head>
<body class="app public">
<a class="skip-link" href="#main"><?= t('Skip to content') ?></a>
<?php include ROOT_PATH . '/include/partials/demo_band.php'; ?>

<header class="site-nav">
    <div class="site-nav-inner">
        <a class="site-brand" href="<?= e($u) ?>index.php"><img src="<?= e($u) ?>assets/img/logo.png" alt=""><?= e(APP_NAME) ?></a>
        <button type="button" class="icon-btn site-menu-toggle" data-nav-toggle aria-controls="site-links" aria-expanded="false" aria-label="<?= t('Open menu') ?>">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="site-links" id="site-links" aria-label="<?= t('Main') ?>">
            <?php include ROOT_PATH . '/include/partials/lang_switch.php'; ?>
            <button type="button" class="icon-btn theme-toggle site-theme-toggle" data-theme-toggle aria-label="<?= t('Switch to dark theme') ?>" title="<?= t('Switch theme') ?>"
                    data-label-dark="<?= t('Switch to dark theme') ?>" data-label-light="<?= t('Switch to light theme') ?>">
                <i class="fa-regular fa-moon" aria-hidden="true"></i><i class="fa-regular fa-sun" aria-hidden="true"></i>
            </button>
            <a class="site-link" href="<?= e($u) ?>index.php"<?= $__cur('home') ?>><?= t('Home') ?></a>
            <a class="site-link" href="<?= e($u) ?>browse_events.php"<?= $__cur('browse') ?>><?= t('Browse events') ?></a>
            <?php if ($__me): ?>
                <a class="site-link" href="<?= e($u) ?>users/my_registrations.php"><?= t('My registrations') ?></a>
                <a class="icon-btn" href="<?= e($u) ?>users/notifications.php" aria-label="<?= $__unread ? t('Notifications ({n} unread)', ['n' => $__unread]) : t('Notifications') ?>">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($__unread): ?><span class="dot"><?= $__unread > 9 ? '9+' : $__unread ?></span><?php endif; ?>
                </a>
                <a class="btn btn-primary" href="<?= e($u) ?><?= is_admin() ? 'admin/index.php' : 'users/dashboard.php' ?>"><?= t('Dashboard') ?></a>
            <?php else: ?>
                <a class="site-link" href="<?= e($u) ?>auth/login.php"<?= $__cur('login') ?>><?= t('Log in') ?></a>
                <a class="btn btn-primary" href="<?= e($u) ?>auth/register.php"><?= t('Create account') ?></a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main">
