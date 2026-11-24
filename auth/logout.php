<?php
/**
 * MyEvent - Log out (POST only, CSRF-checked in config.php).
 * A GET shows a small confirmation form so old links and bookmarks still work.
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!is_logged_in()) {
        redirect('index.php');
    }
    $page_title = 'Log out';
    include ROOT_PATH . '/include/layout/public_start.php';
    ?>
    <section class="section" style="max-width:520px">
        <div class="public-card card-body">
            <h1 class="page-title">Log out?</h1>
            <p class="cell-muted">You will need to log in again to see your registrations.</p>
            <form method="POST" class="form-actions">
                <?= csrf_field() ?>
                <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>users/dashboard.php">Stay logged in</a>
                <button type="submit" class="btn btn-primary">Log out</button>
            </form>
        </div>
    </section>
    <?php
    include ROOT_PATH . '/include/layout/public_end.php';
    exit;
}

end_session();
redirect('index.php');
