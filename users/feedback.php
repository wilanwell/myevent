<?php
/**
 * MyEvent - Rate an event you attended (and edit your rating later).
 *
 * Only participants with an approved registration can give feedback, and only once
 * the event has started. One rating (1-5) and optional comment per person per event.
 * The average is public; comments are shown only to the organiser and admins.
 */
require_once __DIR__ . '/../config.php';

require_login();

$user_id  = current_user_id();
$event_id = (int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0);

$event = db_all($conn,
    "SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.venue, e.poster_path
     FROM events e JOIN registrations r ON r.event_id = e.event_id
     WHERE e.event_id = ? AND r.user_id = ? AND r.approval_status = 'Approved'", 'ii', [$event_id, $user_id])[0] ?? null;

if (!$event) {
    flash('error', 'You can rate an event once your registration for it has been approved.');
    redirect('users/my_registrations.php?show=past');
}
if (strtotime($event['event_date'] . ' ' . $event['event_time']) > time()) {
    flash('info', 'You can rate this event once it has started.');
    redirect('users/my_registrations.php');
}

$existing = db_all($conn, 'SELECT rating, comment FROM feedback WHERE event_id = ? AND user_id = ?', 'ii', [$event_id, $user_id])[0] ?? null;
$rating   = (int) ($existing['rating'] ?? 0);
$comment  = (string) ($existing['comment'] ?? '');
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = (int) ($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        $error = 'Choose a rating from 1 to 5 stars.';
    } elseif (mb_strlen($comment) > 1000) {
        $error = 'Keep your comment to 1000 characters or fewer.';
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO feedback (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)');
        $stmt->bind_param('iiis', $event_id, $user_id, $rating, $comment);
        $stmt->execute();
        $stmt->close();

        flash('success', $existing ? 'Your feedback was updated.' : 'Thanks for your feedback! It helps organisers run better events.');
        redirect('users/my_registrations.php?show=past');
    }
}

$labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];

$page_title = 'Rate event';
$nav_active = 'my_registrations';
include ROOT_PATH . '/include/layout/app_start.php';
?>

<a class="back-link" href="my_registrations.php?show=past"><i class="fa-solid fa-arrow-left"></i> My registrations</a>
<div class="page-header card-narrow">
    <div>
        <h1><?= $existing ? 'Edit your feedback' : 'How was it?' ?></h1>
        <p>Tell the organiser how <strong><?= e($event['event_name']) ?></strong> went. Your rating counts towards the event's public score; only the organiser sees your comment.</p>
    </div>
</div>

<?php if ($error): ?><div class="flash flash-error card-narrow" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="POST" class="card card-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
    <div class="card-body">
        <div class="form-section">
            <div class="event-cell">
                <?= datestub($event['event_date']) ?>
                <div>
                    <div class="event-cell-title"><?= e($event['event_name']) ?></div>
                    <div class="event-cell-meta"><?= e(date('l, j F Y', strtotime($event['event_date']))) ?>, <?= e($event['venue']) ?></div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <fieldset class="field fieldset-reset">
                <legend class="label">Your rating <span class="req" aria-hidden="true">*</span></legend>
                <div class="star-input" role="radiogroup" aria-label="Rating out of 5">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>"<?= $rating === $i ? ' checked' : '' ?> required>
                        <label for="star<?= $i ?>" title="<?= $labels[$i] ?>"><i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only"><?= $i ?> star<?= $i > 1 ? 's' : '' ?>, <?= $labels[$i] ?></span></label>
                    <?php endfor; ?>
                </div>
                <span class="hint">1 = poor, 5 = excellent</span>
            </fieldset>
        </div>

        <div class="form-section">
            <div class="field">
                <label for="fb-comment">Comment <span class="cell-muted">(optional)</span></label>
                <textarea id="fb-comment" name="comment" rows="5" maxlength="1000" placeholder="What worked well? What could be better next time?"><?= e($comment) ?></textarea>
                <span class="hint">Only the organiser and administrators can read this.</span>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="my_registrations.php?show=past">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= $existing ? 'Update feedback' : 'Send feedback' ?></button>
        </div>
    </div>
</form>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
