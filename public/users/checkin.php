<?php
/**
 * MyEvent - Check a participant in from their ticket.
 *
 * Organisers scan the QR on a ticket with their phone camera; it opens this page with ?code=...
 * The code is HMAC-signed (see ticket_code()), so it cannot be guessed or edited.
 * Looking up a code is read-only; checking in requires a POST with a CSRF token.
 * Only the event's organiser or an administrator can check people in.
 */
require_once __DIR__ . '/../../config.php';

if (!is_logged_in()) {
    flash('info', __('Log in as the event organiser to check this ticket in.'));
    redirect('auth/login.php');
}

$code   = trim((string) ($_POST['code'] ?? $_GET['code'] ?? ''));
$ticket = $code !== '' ? verify_ticket_code($code) : null;
$reg    = null;
$problem = '';

if ($code !== '' && !$ticket) {
    $problem = __('This ticket code is not valid. Check it was typed exactly as printed.');
} elseif ($ticket) {
    $reg = db_all($conn,
        "SELECT r.reg_id, r.event_id, r.approval_status, r.attendance_status, r.checked_in_at,
                u.name, u.email, u.category, e.event_name, e.event_date, e.event_time, e.venue, e.created_by
         FROM registrations r
         JOIN users u ON u.user_id = r.user_id
         JOIN events e ON e.event_id = r.event_id
         WHERE r.reg_id = ? AND r.event_id = ?", 'ii', [$ticket['reg_id'], $ticket['event_id']])[0] ?? null;

    if (!$reg) {
        $problem = __('This ticket no longer exists. The registration may have been cancelled.');
    } elseif (!is_admin() && (int) $reg['created_by'] !== current_user_id()) {
        // Do not reveal participant details to people who do not run the event.
        $reg = null;
        $problem = __('Only the organiser of this event can check tickets in.');
    } elseif ($reg['approval_status'] !== 'Approved') {
        $problem = $reg['approval_status'] === 'Pending'
            ? __('This registration is still pending, not approved. Do not admit until it is approved.')
            : __('This registration was rejected, not approved. Do not admit this person.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reg && $problem === '') {
    if ($reg['attendance_status'] !== 'Present') {
        $stmt = $conn->prepare("UPDATE registrations SET attendance_status = 'Present', checked_in_at = NOW() WHERE reg_id = ?");
        $stmt->bind_param('i', $reg['reg_id']);
        $stmt->execute();
        $stmt->close();
        audit($conn, 'registration.checkin', 'registration', (int) $reg['reg_id'], $reg['name'] . ' · ' . $reg['event_name']);
        flash('success', __('{name} is checked in.', ['name' => $reg['name']]));
    } else {
        flash('info', __('{name} was already checked in.', ['name' => $reg['name']]));
    }
    redirect('users/checkin.php?code=' . rawurlencode(ticket_code((int) $reg['event_id'], (int) $reg['reg_id'])));
}

$page_title = __('Check in');
$nav_active = 'checkin';
$nav_area   = is_admin() && $reg && (int) $reg['created_by'] !== current_user_id() ? 'admin' : 'auto';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<div class="page-header">
    <div>
        <h1><?= t('Check in') ?></h1>
        <p><?= t('Scan the QR code on a participant\'s ticket with your phone camera, or type the ticket code.') ?></p>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-header"><h2 class="card-title"><?= t('Ticket') ?></h2></div>
        <div class="card-body stack">
            <?php if ($problem): ?>
                <div class="status-box flash-error" role="alert"><i class="fa-solid fa-circle-xmark"></i> <?= e($problem) ?></div>
            <?php endif; ?>

            <?php if ($reg): ?>
                <?php $present = $reg['attendance_status'] === 'Present'; ?>
                <div class="event-cell">
                    <span class="avatar avatar-lg" aria-hidden="true"><?= e(initials($reg['name'])) ?></span>
                    <div>
                        <div class="card-title"><?= e($reg['name']) ?></div>
                        <div class="event-cell-meta"><?= e($reg['email']) ?> &middot; <?= e(status_label($reg['category'])) ?></div>
                        <div class="event-cell-meta"><?= e($reg['event_name']) ?>, <?= e(ldate('j M Y', $reg['event_date'])) ?></div>
                    </div>
                </div>

                <?php if ($present): ?>
                    <div class="status-box flash-success"><i class="fa-solid fa-circle-check"></i>
                        <?= $reg['checked_in_at'] ? t('Checked in at {time}.', ['time' => ldate('g:i A, j M', $reg['checked_in_at'])]) : t('Checked in.') ?>
                    </div>
                <?php elseif ($problem === ''): ?>
                    <?php if ($reg['event_date'] !== date('Y-m-d')): ?>
                        <div class="status-box flash-info"><i class="fa-regular fa-calendar"></i> <?= t('This event is on {date}, not today. Check in only if that is intended.', ['date' => ldate('j M Y', $reg['event_date'])]) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= e($code) ?>">
                        <button type="submit" class="btn btn-success btn-block btn-lg"><i class="fa-solid fa-check"></i> <?= t('Check in {name}', ['name' => strtok($reg['name'], ' ')]) ?></button>
                    </form>
                <?php endif; ?>

                <p class="cell-muted no-margin">
                    <a href="<?= e(BASE_URL . ((int) $reg['created_by'] === current_user_id() ? 'users/manage_participants.php' : 'admin/organizer/manage_participants.php')) ?>?event_id=<?= (int) $reg['event_id'] ?>"><?= t('Open participant list') ?></a>
                </p>
            <?php elseif (!$problem): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-qrcode"></i>
                    <h3><?= t('Waiting for a ticket') ?></h3>
                    <p><?= t('Point your phone camera at the QR code on the ticket. The link opens this page with the participant\'s details.') ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title"><?= t('Enter a code') ?></h2></div>
        <div class="card-body">
            <form method="GET" class="form-grid form-stack">
                <div class="field">
                    <label for="ci-code"><?= t('Ticket code') ?></label>
                    <input id="ci-code" type="text" name="code" value="<?= e($code) ?>" placeholder="<?= t('ME-0044-00012-7F3A9C') ?>" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                    <span class="hint"><?= t('Printed under the QR code on the ticket.') ?></span>
                </div>
                <button type="submit" class="btn btn-primary"><?= t('Look up ticket') ?></button>
            </form>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
