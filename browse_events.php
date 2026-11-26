<?php
/**
 * MyEvent - Browse and search public events.
 */
require_once __DIR__ . '/config.php';

$q        = trim((string) ($_GET['q'] ?? ''));
$category = (int) ($_GET['category'] ?? ($_GET['category_id'] ?? 0));
$show     = ($_GET['show'] ?? '') === 'all' ? 'all' : 'open';

$where  = "e.visibility = 'Public' AND e.event_date >= CURDATE()";
$types  = '';
$params = [];
if ($show === 'open') {
    $where .= ' AND COALESCE(e.reg_close_date, e.event_date) >= CURDATE()';
}
if ($q !== '') {
    $where .= ' AND (e.event_name LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)';
    $types .= 'sss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($category > 0) {
    $where .= ' AND e.category_id = ?';
    $types .= 'i';
    $params[] = $category;
}

$from  = 'FROM events e LEFT JOIN event_category c ON c.category_id = e.category_id';
$total = (int) db_value($conn, "SELECT COUNT(*) $from WHERE $where", $types, $params);
$p     = paginate($total, 12);
$events = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.venue, e.mode, e.fee, e.poster_path,
            e.max_participants, e.reg_close_date, c.categoryName,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken
     $from WHERE $where
     ORDER BY e.event_date ASC, e.event_time ASC
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$p['per_page'], $p['offset']]));

$categories = db_all($conn, 'SELECT category_id, categoryName FROM event_category ORDER BY categoryName');

$page_title  = __('Browse events');
$nav_current = 'browse';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<section class="section">
    <?php include ROOT_PATH . '/include/flash.php'; ?>
    <div class="section-head">
        <div>
            <h1 class="page-title"><?= t('Browse events') ?></h1>
            <p><?php
                if ($show === 'open') {
                    $__summary = $q !== '' ? _n('{n} event open for registration matching “{q}”.', '{n} events open for registration matching “{q}”.', $total, ['q' => $q])
                                           : _n('{n} event open for registration.', '{n} events open for registration.', $total);
                } else {
                    $__summary = $q !== '' ? _n('{n} event coming up matching “{q}”.', '{n} events coming up matching “{q}”.', $total, ['q' => $q])
                                           : _n('{n} event coming up.', '{n} events coming up.', $total);
                }
                echo e($__summary);
            ?></p>
        </div>
        <form class="toolbar toolbar-end" method="GET" role="search">
            <?php if ($category): ?><input type="hidden" name="category" value="<?= $category ?>"><?php endif; ?>
            <label class="search-box">
                <span class="sr-only"><?= t('Search events') ?></span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Name, venue or topic') ?>">
            </label>
            <label class="sr-only" for="b-show"><?= t('Show') ?></label>
            <select id="b-show" class="input" name="show" data-autosubmit>
                <option value="open"<?= $show === 'open' ? ' selected' : '' ?>><?= t('Open for registration') ?></option>
                <option value="all"<?= $show === 'all' ? ' selected' : '' ?>><?= t('All upcoming') ?></option>
            </select>
            <button class="btn btn-primary" type="submit"><?= t('Search') ?></button>
        </form>
    </div>

    <nav class="chips" aria-label="<?= t('Categories') ?>">
        <a class="chip<?= $category === 0 ? ' is-active' : '' ?>" href="<?= e(url_with(['category' => null, 'category_id' => null, 'page' => null])) ?>"<?= $category === 0 ? ' aria-current="true"' : '' ?>><?= t('All categories') ?></a>
        <?php foreach ($categories as $c): $on = $category === (int) $c['category_id']; ?>
            <a class="chip<?= $on ? ' is-active' : '' ?>" href="<?= e(url_with(['category' => (int) $c['category_id'], 'category_id' => null, 'page' => null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($c['categoryName']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($events): ?>
        <div class="event-grid">
            <?php foreach ($events as $ev) { include ROOT_PATH . '/include/partials/event_card.php'; } ?>
        </div>
        <?= render_pagination($p, 'events') ?>
    <?php else: ?>
        <div class="public-card empty-state">
            <i class="fa-solid fa-magnifying-glass"></i>
            <h3><?= t('No events match your search') ?></h3>
            <p><?= t('Try another keyword or category, or include events that have closed registration.') ?></p>
            <a class="btn btn-secondary" href="browse_events.php"><?= t('Clear filters') ?></a>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
