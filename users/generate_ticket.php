<?php
/**
 * MyEvent - Printable ticket for an approved registration.
 */
require_once __DIR__ . '/../config.php';

require_login();

$event_id = (int) ($_GET['event_id'] ?? 0);
$data = db_all($conn,
    "SELECT e.event_name, e.event_date, e.event_time, e.venue, e.mode, e.remarks,
            u.name AS user_name, u.email, r.reg_id, r.registration_date, org.name AS organiser
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     JOIN users u ON u.user_id = r.user_id
     LEFT JOIN users org ON org.user_id = e.created_by
     WHERE r.event_id = ? AND r.user_id = ? AND r.approval_status = 'Approved'",
    'ii', [$event_id, current_user_id()])[0] ?? null;

if (!$data) {
    flash('error', 'Your ticket is available once the organiser approves your registration.');
    redirect('users/my_registrations.php');
}

// Human-readable ticket code the organiser can match against the participant list.
$code = sprintf('ME-%04d-%05d', $event_id, (int) $data['reg_id']);
$ts = strtotime($data['event_date']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket: <?= e($data['event_name']) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/app.css?v=3">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/public.css?v=3">
    <style>
        body { padding: 40px 20px; }
        .ticket-page { max-width: 640px; margin: 0 auto; }
        .ticket-page .ticket { transform: none; box-shadow: 0 20px 40px -20px rgba(28, 33, 64, 0.35); border: 1px solid var(--line); }
        .ticket-page .ticket-stub::before, .ticket-page .ticket-stub::after { background: var(--paper); }
        .ticket-code { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--line); display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .ticket-code b { font-size: var(--fs-xl); letter-spacing: 0.06em; color: var(--ink); font-variant-numeric: tabular-nums; }
        .ticket-actions { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 20px; }
        .ticket-note { color: var(--muted); font-size: var(--fs-sm); margin-top: 16px; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .ticket-actions { display: none; }
            .ticket-page .ticket { box-shadow: none; }
            .ticket-stub { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="app">
<div class="ticket-page">
    <div class="ticket-actions">
        <a class="btn btn-ghost" href="my_registrations.php"><i class="fa-solid fa-arrow-left"></i> My registrations</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print or save as PDF</button>
    </div>

    <article class="ticket" aria-label="Ticket for <?= e($data['event_name']) ?>">
        <div class="ticket-stub" aria-hidden="true">
            <span class="ticket-weekday"><?= date('l', $ts) ?></span>
            <span class="ticket-day"><?= date('j', $ts) ?></span>
            <span class="ticket-month"><?= date('M Y', $ts) ?></span>
        </div>
        <div class="ticket-body">
            <span class="ticket-label"><?= e(APP_NAME) ?> admission ticket</span>
            <h1 class="ticket-title"><?= e($data['event_name']) ?></h1>
            <ul class="ticket-facts">
                <li><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(date('l, j F Y', $ts)) ?>, <?= e(date('g:i A', strtotime($data['event_time']))) ?></li>
                <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e($data['venue']) ?> (<?= e($data['mode']) ?>)</li>
                <li><i class="fa-regular fa-user" aria-hidden="true"></i> <?= e($data['user_name']) ?>, <?= e($data['email']) ?></li>
                <?php if ($data['organiser']): ?><li><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Organised by <?= e($data['organiser']) ?></li><?php endif; ?>
            </ul>
            <div class="ticket-code">
                <span><span class="cell-muted">Ticket code</span><br><b><?= e($code) ?></b></span>
                <span><span class="cell-muted">Approved registration from</span><br><?= e(date('j M Y', strtotime($data['registration_date']))) ?></span>
            </div>
        </div>
    </article>

    <?php if (trim((string) $data['remarks']) !== ''): ?>
        <p class="ticket-note"><strong>Before you come:</strong> <?= e($data['remarks']) ?></p>
    <?php endif; ?>
    <p class="ticket-note">Show this ticket on your phone or printed at the entrance.</p>
</div>
</body>
</html>
