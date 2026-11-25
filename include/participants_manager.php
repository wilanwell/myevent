<?php
/**
 * MyEvent - Shared participant management screen (organisers and admins).
 *
 * The including page sets (after checking access to the event):
 *   $pm_event      array   Event row (event_id, event_name, event_date, max_participants)
 *   $pm_self_url   string  Path of this screen relative to BASE_URL, without query string
 *   $pm_switcher   array   Optional [event_id => event_name] list for the event picker
 *   $page_title, $nav_active, $nav_area   Layout settings.
 */
$event_id = (int) $pm_event['event_id'];
$self = $pm_self_url . '?event_id=' . $event_id;

/* ---------- Actions (POST, CSRF-checked in config.php) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_id = (int) ($_POST['reg_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    $reg = db_all($conn, 'SELECT user_id, approval_status FROM registrations WHERE reg_id = ? AND event_id = ?', 'ii', [$reg_id, $event_id])[0] ?? null;

    $updates = [
        'approve' => ["approval_status = 'Approved'", "✅ Your registration for <strong>%s</strong> has been approved. Your ticket is ready in My registrations.", 'Registration approved.'],
        'reject'  => ["approval_status = 'Rejected'", "Your registration for <strong>%s</strong> was not approved.", 'Registration rejected.'],
        'present' => ["attendance_status = 'Present', checked_in_at = NOW()", null, 'Checked in.'],
        'absent'  => ["attendance_status = 'Absent', checked_in_at = NULL", null, 'Check-in undone.'],
    ];

    if (!$reg || !isset($updates[$action])) {
        flash('error', 'That registration could not be updated.');
        redirect($self);
    }
    if (in_array($action, ['present', 'absent'], true) && $reg['approval_status'] !== 'Approved') {
        flash('error', 'Only approved participants can be marked for attendance.');
        redirect($self);
    }

    [$set, $notice, $done] = $updates[$action];
    $stmt = $conn->prepare("UPDATE registrations SET $set WHERE reg_id = ? AND event_id = ?");
    $stmt->bind_param('ii', $reg_id, $event_id);
    $stmt->execute();
    $stmt->close();

    audit($conn, 'registration.' . $action, 'registration', $reg_id, $pm_event['event_name']);

    if ($action === 'reject' && $reg['approval_status'] !== 'Rejected') {
        $promotedCount = promote_waitlist($conn, $event_id);
    }

    if ($notice) {
        $full = db_all($conn, 'SELECT event_id, event_name, event_date, event_time, end_time, venue, mode FROM events WHERE event_id = ?', 'i', [$event_id])[0];
        $email = $action === 'approve' ? [
            'subject' => "You're in: " . $full['event_name'],
            'heading' => 'Your registration is approved',
            'intro'   => "Good news: the organiser approved your place at {$full['event_name']}. Your ticket with its QR code is ready.",
            'facts'   => event_facts($full),
            'button'  => ['View my ticket', BASE_URL . 'users/generate_ticket.php?event_id=' . $event_id],
            'footnote' => 'Show the ticket on your phone or printed at the entrance.',
        ] : [
            'subject' => 'Registration update: ' . $full['event_name'],
            'heading' => 'Your registration was not approved',
            'intro'   => "The organiser was not able to offer you a place at {$full['event_name']} this time.",
            'button'  => ['Browse other events', BASE_URL . 'browse_events.php'],
        ];
        notify_user($conn, (int) $reg['user_id'], sprintf($notice, $full['event_name']), 'participant', $email);
    }
    flash('success', $done . (!empty($promotedCount) ? ' ' . $promotedCount . ' ' . ($promotedCount === 1 ? 'person' : 'people') . ' moved up from the waitlist.' : ''));
    redirect($self . (isset($_GET['status']) ? '&status=' . urlencode((string) $_GET['status']) : ''));
}

/* ---------- Filters ---------- */
$status = (string) ($_GET['status'] ?? '');
$status = in_array($status, ['Pending', 'Approved', 'Rejected'], true) ? $status : '';
$q      = trim((string) ($_GET['q'] ?? ''));

$where  = 'r.event_id = ?';
$types  = 'i';
$params = [$event_id];
if ($status !== '') {
    $where .= ' AND r.approval_status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($q !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR r.reg_id = ?)';
    $types .= 'ssi';
    $like = '%' . $q . '%';
    // A full ticket code (ME-0044-00012-7F3A9C) finds that registration directly.
    $ticketReg = ($t = verify_ticket_code($q)) && $t['event_id'] === $event_id ? $t['reg_id'] : 0;
    array_push($params, $like, $like, $ticketReg);
}

/* ---------- CSV export (current filter) ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all($conn,
        "SELECT u.name, u.email, u.phone, u.category, r.registration_date, r.approval_status, r.attendance_status
         FROM registrations r JOIN users u ON u.user_id = r.user_id
         WHERE $where ORDER BY u.name", $types, $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . safe_filename('Participants_' . $pm_event['event_name']) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
    fputcsv($out, ['Name', 'Email', 'Phone', 'Category', 'Registered at', 'Approval', 'Attendance']);
    foreach ($rows as $row) {
        fputcsv($out, csv_row($row));
    }
    fclose($out);
    exit;
}

/* ---------- Data ---------- */
$counts = ['' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach (db_all($conn, 'SELECT approval_status, COUNT(*) n FROM registrations WHERE event_id = ? GROUP BY approval_status', 'i', [$event_id]) as $c) {
    $counts[$c['approval_status']] = (int) $c['n'];
    $counts[''] += (int) $c['n'];
}
$present = (int) db_value($conn, "SELECT COUNT(*) FROM registrations WHERE event_id = ? AND attendance_status = 'Present'", 'i', [$event_id]);

$total = (int) db_value($conn, "SELECT COUNT(*) FROM registrations r JOIN users u ON u.user_id = r.user_id WHERE $where", $types, $params);
$p = paginate($total, 15);
$rows = db_all($conn,
    "SELECT r.reg_id, r.registration_date, r.approval_status, r.attendance_status, u.name, u.email, u.category
     FROM registrations r JOIN users u ON u.user_id = r.user_id
     WHERE $where
     ORDER BY FIELD(r.approval_status, 'Pending', 'Approved', 'Rejected'), r.registration_date
     LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$p['per_page'], $p['offset']]));

$capacity = (int) $pm_event['max_participants'];
$rating   = event_rating($conn, $event_id);
$reviews  = db_all($conn,
    'SELECT f.rating, f.comment, f.created_at, f.updated_at, u.name FROM feedback f JOIN users u ON u.user_id = f.user_id
     WHERE f.event_id = ? ORDER BY COALESCE(f.updated_at, f.created_at) DESC', 'i', [$event_id]);
$waitlist = db_all($conn,
    'SELECT u.name, u.email, w.created_at FROM waitlist w JOIN users u ON u.user_id = w.user_id
     WHERE w.event_id = ? ORDER BY w.created_at, w.waitlist_id', 'i', [$event_id]);

include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pm_event['event_name']) ?></h1>
        <p>
            <?= e(date('l, j F Y', strtotime($pm_event['event_date']))) ?>.
            <?= $counts['Approved'] ?> approved<?= $capacity > 0 ? ' of ' . $capacity . ' places' : '' ?>, <?= $present ?> checked in.
        </p>
    </div>
    <div class="page-header-actions">
        <?php if (!empty($pm_switcher) && count($pm_switcher) > 1): ?>
            <form method="GET">
                <label class="sr-only" for="pm-switch">Switch event</label>
                <select id="pm-switch" class="input" name="event_id" data-autosubmit>
                    <?php foreach ($pm_switcher as $id => $name): ?>
                        <option value="<?= (int) $id ?>"<?= (int) $id === $event_id ? ' selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-secondary" type="submit">Go</button></noscript>
            </form>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>users/checkin.php"><i class="fa-solid fa-qrcode"></i> Check in</a>
        <a class="btn btn-secondary" href="<?= e(url_with(['export' => 'csv', 'page' => null])) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>event_detail.php?id=<?= $event_id ?>">View event page</a>
    </div>
</div>

<div class="stats">
    <a class="stat" href="<?= e(url_with(['status' => null, 'page' => null])) ?>"><span class="stat-icon"><i class="fa-solid fa-users"></i></span><span><span class="stat-value"><?= $counts[''] ?></span><br><span class="stat-label">Registrations</span></span></a>
    <a class="stat" href="<?= e(url_with(['status' => 'Pending', 'page' => null])) ?>"><span class="stat-icon is-amber"><i class="fa-regular fa-clock"></i></span><span><span class="stat-value"><?= $counts['Pending'] ?></span><br><span class="stat-label">To review</span></span></a>
    <a class="stat" href="<?= e(url_with(['status' => 'Approved', 'page' => null])) ?>"><span class="stat-icon is-green"><i class="fa-solid fa-circle-check"></i></span><span><span class="stat-value"><?= $counts['Approved'] ?></span><br><span class="stat-label">Approved</span></span></a>
    <div class="stat"><span class="stat-icon is-sky"><i class="fa-solid fa-clipboard-check"></i></span><span><span class="stat-value"><?= $present ?></span><br><span class="stat-label">Checked in</span></span></div>
</div>

<section class="card">
    <div class="card-header">
        <form class="toolbar" method="GET" role="search">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <label class="search-box">
                <span class="sr-only">Search participants</span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or ticket code">
            </label>
            <label class="sr-only" for="pm-status">Status</label>
            <select id="pm-status" class="input" name="status" data-autosubmit>
                <option value="">All statuses</option>
                <?php foreach (['Pending' => 'To review', 'Approved' => 'Approved', 'Rejected' => 'Rejected'] as $val => $label): ?>
                    <option value="<?= $val ?>"<?= $status === $val ? ' selected' : '' ?>><?= $label ?> (<?= $counts[$val] ?>)</option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit">Apply</button>
        </form>
    </div>

    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Participant</th>
                        <th scope="col">Registered</th>
                        <th scope="col">Status</th>
                        <th scope="col">Attendance</th>
                        <th scope="col" class="actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="event-cell person-cell">
                                <span class="avatar" aria-hidden="true"><?= e(initials($row['name'])) ?></span>
                                <div>
                                    <div class="event-cell-title"><?= e($row['name']) ?></div>
                                    <div class="event-cell-meta"><?= e($row['email']) ?> &middot; <?= e($row['category']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="cell-muted"><?= e(date('j M Y', strtotime($row['registration_date']))) ?><span class="cell-sub" title="Ticket code"><?= e(ticket_code($event_id, (int) $row['reg_id'])) ?></span></td>
                        <td><?= status_badge($row['approval_status']) ?></td>
                        <td>
                            <?php if ($row['approval_status'] === 'Approved'): ?>
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reg_id" value="<?= (int) $row['reg_id'] ?>">
                                    <?php if ($row['attendance_status'] === 'Present'): ?>
                                        <button class="btn btn-sm btn-success" name="action" value="absent" title="Undo check-in"><i class="fa-solid fa-check"></i> Present</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" name="action" value="present">Check in</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="cell-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reg_id" value="<?= (int) $row['reg_id'] ?>">
                                <?php if ($row['approval_status'] !== 'Approved'): ?>
                                    <button class="btn btn-sm btn-primary" name="action" value="approve">Approve</button>
                                <?php endif; ?>
                                <?php if ($row['approval_status'] !== 'Rejected'): ?>
                                    <button class="btn btn-sm btn-danger" name="action" value="reject">Reject</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($p, 'participants') ?>
    <?php elseif ($q !== '' || $status !== ''): ?>
        <div class="empty-state">
            <i class="fa-solid fa-filter"></i>
            <h3>No participants match these filters</h3>
            <a class="btn btn-secondary" href="<?= e(BASE_URL . $self) ?>">Clear filters</a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-user-plus"></i>
            <h3>No one has registered yet</h3>
            <p>Share the event page so people can sign up. Registrations will appear here for review.</p>
        </div>
    <?php endif; ?>
</section>

<?php if ($reviews): ?>
<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Feedback</h2>
            <p class="card-subtitle">Only you and administrators can read comments. The average is shown on the event page.</p>
        </div>
        <span class="rating-summary"><?= rating_stars($rating['avg']) ?> <b><?= e(number_format($rating['avg'], 1)) ?></b> from <?= $rating['count'] ?></span>
    </div>
    <?php foreach ($reviews as $rv): ?>
        <article class="review">
            <div class="review-head">
                <span class="event-cell-title"><?= e($rv['name']) ?></span>
                <span><?= rating_stars((float) $rv['rating']) ?> <span class="cell-muted"><?= e(date('j M Y', strtotime($rv['updated_at'] ?? $rv['created_at']))) ?></span></span>
            </div>
            <?php if (trim((string) $rv['comment']) !== ''): ?><p><?= nl2br(e($rv['comment'])) ?></p><?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($waitlist): ?>
<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Waitlist (<?= count($waitlist) ?>)</h2>
            <p class="card-subtitle">When a place frees up, the first person here is registered automatically and appears above for approval.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col" class="num">#</th><th scope="col">Person</th><th scope="col">Joined the waitlist</th></tr></thead>
            <tbody>
            <?php foreach ($waitlist as $i => $w): ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td><div class="event-cell-title"><?= e($w['name']) ?></div><div class="event-cell-meta"><?= e($w['email']) ?></div></td>
                    <td class="cell-muted"><?= e(date('j M Y, g:i A', strtotime($w['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
