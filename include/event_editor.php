<?php
/**
 * MyEvent - Shared create/edit event screen (organisers and admins).
 *
 * The including page sets:
 *   $editor_event     ?array  Existing event row when editing, null when creating.
 *   $editor_redirect  string  Where to go after saving (path relative to BASE_URL).
 *   $editor_cancel    string  URL for the Cancel button.
 *   $page_title, $nav_active, $nav_area   Layout settings.
 *
 * Ownership must already be checked by the including page.
 */

/** Read and validate event fields from $_POST. @return array{0: array, 1: string[]} */
function event_input(mysqli $conn): array
{
    $d = [
        'event_name'       => trim((string) ($_POST['event_name'] ?? '')),
        'description'      => trim((string) ($_POST['description'] ?? '')),
        'category_id'      => (int) ($_POST['category_id'] ?? 0),
        'contact_no'       => trim((string) ($_POST['contact_no'] ?? '')),
        'venue'            => trim((string) ($_POST['venue'] ?? '')),
        'event_date'       => (string) ($_POST['event_date'] ?? ''),
        'event_time'       => (string) ($_POST['event_time'] ?? ''),
        'mode'             => (string) ($_POST['mode'] ?? ''),
        'reg_close_date'   => (string) ($_POST['reg_close_date'] ?? ''),
        'max_participants' => max(0, (int) ($_POST['max_participants'] ?? 0)),
        'fee'              => parse_fee($_POST['fee'] ?? 0),
        'remarks'          => trim((string) ($_POST['remarks'] ?? '')),
        'visibility'       => (string) ($_POST['visibility'] ?? 'Public'),
    ];

    $errors = [];
    if ($d['event_name'] === '' || mb_strlen($d['event_name']) > 255) {
        $errors['event_name'] = 'Enter an event name (up to 255 characters).';
    }
    if ($d['venue'] === '' || mb_strlen($d['venue']) > 255) {
        $errors['venue'] = 'Enter where the event takes place.';
    }
    if (!valid_date($d['event_date'])) {
        $errors['event_date'] = 'Choose the event date.';
    }
    if (!valid_time($d['event_time'])) {
        $errors['event_time'] = 'Choose a start time.';
    }
    if (!in_array($d['mode'], ['Physical', 'Online', 'Hybrid'], true)) {
        $errors['mode'] = 'Choose physical, online or hybrid.';
    }
    if (!in_array($d['visibility'], ['Public', 'Private'], true)) {
        $errors['visibility'] = 'Choose who can find this event.';
    }
    if (!db_value($conn, 'SELECT 1 FROM event_category WHERE category_id = ?', 'i', [$d['category_id']])) {
        $errors['category_id'] = 'Choose a category.';
    }
    if ($d['reg_close_date'] === '') {
        $d['reg_close_date'] = $d['event_date'];
    } elseif (!valid_date($d['reg_close_date'])) {
        $errors['reg_close_date'] = 'Choose a valid closing date.';
    } elseif (valid_date($d['event_date']) && $d['reg_close_date'] > $d['event_date']) {
        $errors['reg_close_date'] = 'Registration must close on or before the event date.';
    }
    if ($d['contact_no'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $d['contact_no'])) {
        $errors['contact_no'] = 'Enter a phone number with 7 to 20 digits.';
    }
    return [$d, $errors];
}

$editing  = !empty($editor_event);
$data     = $editing ? $editor_event : [
    'event_name' => '', 'description' => '', 'category_id' => 0, 'contact_no' => '', 'venue' => '',
    'event_date' => '', 'event_time' => '', 'mode' => 'Physical', 'reg_close_date' => '',
    'max_participants' => 0, 'fee' => 0, 'remarks' => '', 'visibility' => 'Public', 'poster_path' => null,
];
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$input, $errors] = event_input($conn);
    $data = array_merge($data, $input);

    if (!$errors) {
        [$newPoster, $uploadError] = upload_image($_FILES['poster'] ?? null, POSTER_DIR, 'event');
        if ($uploadError) {
            $errors['poster'] = $uploadError;
        }
    }

    if (!$errors) {
        $poster = $newPoster ?? ($editing ? $editor_event['poster_path'] : null);
        $cols = [$data['event_name'], $data['description'], $data['category_id'], $data['contact_no'], $data['venue'],
                 $data['event_date'], $data['event_time'], $data['mode'], $data['reg_close_date'],
                 $data['max_participants'], $data['fee'], $data['remarks'], $data['visibility'], $poster];
        $types = 'ssissssssidsss';

        if ($editing) {
            $stmt = $conn->prepare(
                'UPDATE events SET event_name = ?, description = ?, category_id = ?, contact_no = ?, venue = ?,
                        event_date = ?, event_time = ?, mode = ?, reg_close_date = ?, max_participants = ?,
                        fee = ?, remarks = ?, visibility = ?, poster_path = ?
                 WHERE event_id = ?');
            $cols[] = (int) $editor_event['event_id'];
            $stmt->bind_param($types . 'i', ...$cols);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO events (event_name, description, category_id, contact_no, venue, event_date, event_time,
                                     mode, reg_close_date, max_participants, fee, remarks, visibility, poster_path, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $cols[] = current_user_id();
            $stmt->bind_param($types . 'i', ...$cols);
        }
        $stmt->execute();
        $stmt->close();

        if ($newPoster && $editing) {
            delete_upload($editor_event['poster_path'], POSTER_DIR);
        }
        flash('success', $editing ? 'Event updated.' : 'Event published.');
        redirect($editor_redirect);
    }
}

$categories = db_all($conn, 'SELECT category_id, categoryName FROM event_category ORDER BY categoryName');

/** Field error message, if any. */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<span class="hint field-error" id="err-' . e($field) . '">' . e($errors[$field]) . '</span>' : '';
};
$invalid = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
};

include ROOT_PATH . '/include/layout/app_start.php';
?>

<a class="back-link" href="<?= e($editor_cancel) ?>"><i class="fa-solid fa-arrow-left"></i> Back</a>
<div class="page-header">
    <div>
        <h1><?= $editing ? 'Edit event' : 'Create an event' ?></h1>
        <p><?= $editing ? 'Changes are visible to participants straight away.' : 'Fill in the details participants need to decide and register.' ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">Please fix the highlighted fields and try again.</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="card card-narrow" novalidate>
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-section">
            <h2 class="form-section-title">About the event</h2>
            <p class="form-section-desc">The name and description appear on the event page.</p>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="ev-name">Event name <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-name" type="text" name="event_name" value="<?= e($data['event_name']) ?>" maxlength="255" required<?= $invalid('event_name') ?>>
                    <?= $err('event_name') ?>
                </div>
                <div class="field">
                    <label for="ev-cat">Category <span class="req" aria-hidden="true">*</span></label>
                    <select id="ev-cat" name="category_id" required<?= $invalid('category_id') ?>>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['category_id'] ?>"<?= (int) $data['category_id'] === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['categoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('category_id') ?>
                </div>
                <div class="field">
                    <label for="ev-vis">Who can find it</label>
                    <select id="ev-vis" name="visibility"<?= $invalid('visibility') ?>>
                        <option value="Public"<?= $data['visibility'] === 'Public' ? ' selected' : '' ?>>Public: listed for everyone</option>
                        <option value="Private"<?= $data['visibility'] === 'Private' ? ' selected' : '' ?>>Private: only people with the link</option>
                    </select>
                </div>
                <div class="field span-2">
                    <label for="ev-desc">Description</label>
                    <textarea id="ev-desc" name="description" rows="4"><?= e($data['description']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="form-section-title">When and where</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="ev-date">Date <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-date" type="date" name="event_date" value="<?= e($data['event_date']) ?>" required<?= $invalid('event_date') ?>>
                    <?= $err('event_date') ?>
                </div>
                <div class="field">
                    <label for="ev-time">Start time <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-time" type="time" name="event_time" value="<?= e(substr((string) $data['event_time'], 0, 5)) ?>" required<?= $invalid('event_time') ?>>
                    <?= $err('event_time') ?>
                </div>
                <div class="field">
                    <label for="ev-venue">Venue <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-venue" type="text" name="venue" value="<?= e($data['venue']) ?>" maxlength="255" placeholder="e.g. Dewan Canselor or a meeting link" required<?= $invalid('venue') ?>>
                    <?= $err('venue') ?>
                </div>
                <div class="field">
                    <span class="label" id="ev-mode-label">Mode <span class="req" aria-hidden="true">*</span></span>
                    <div class="choice-row" role="radiogroup" aria-labelledby="ev-mode-label">
                        <?php foreach (['Physical', 'Online', 'Hybrid'] as $m): ?>
                            <label class="choice"><input type="radio" name="mode" value="<?= $m ?>"<?= $data['mode'] === $m ? ' checked' : '' ?>> <?= $m ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('mode') ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="form-section-title">Registration</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="ev-close">Registration closes</label>
                    <input id="ev-close" type="date" name="reg_close_date" value="<?= e($data['reg_close_date']) ?>"<?= $invalid('reg_close_date') ?>>
                    <?= $err('reg_close_date') ?: '<span class="hint">Leave empty to close on the event date.</span>' ?>
                </div>
                <div class="field">
                    <label for="ev-max">Places available</label>
                    <input id="ev-max" type="number" name="max_participants" min="0" step="1" value="<?= (int) $data['max_participants'] ?: '' ?>" placeholder="Unlimited">
                    <span class="hint">Leave empty for no limit.</span>
                </div>
                <div class="field">
                    <label for="ev-fee">Fee (RM)</label>
                    <input id="ev-fee" type="number" name="fee" min="0" step="0.01" value="<?= (float) $data['fee'] > 0 ? e(number_format((float) $data['fee'], 2, '.', '')) : '' ?>" placeholder="Free">
                    <span class="hint">Leave empty for a free event.</span>
                </div>
                <div class="field">
                    <label for="ev-contact">Contact number</label>
                    <input id="ev-contact" type="tel" name="contact_no" value="<?= e($data['contact_no']) ?>" maxlength="20" placeholder="012-3456789"<?= $invalid('contact_no') ?>>
                    <?= $err('contact_no') ?>
                </div>
                <div class="field span-2">
                    <label for="ev-remarks">Notes for participants</label>
                    <textarea id="ev-remarks" name="remarks" rows="2" placeholder="What to bring, dress code, parking…"><?= e($data['remarks']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="form-section-title">Poster</h2>
            <div class="poster-field">
                <img class="poster-preview" id="posterPreview" src="<?= e(poster_url($data['poster_path'] ?? null)) ?>" alt="Poster preview">
                <div class="field">
                    <label for="ev-poster"><?= $editing ? 'Replace poster' : 'Upload a poster' ?></label>
                    <input id="ev-poster" type="file" name="poster" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#posterPreview"<?= $invalid('poster') ?>>
                    <?= $err('poster') ?: '<span class="hint">JPG, PNG, GIF or WEBP, up to 5&nbsp;MB. Landscape 16:9 looks best.</span>' ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="<?= e($editor_cancel) ?>">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Publish event' ?></button>
        </div>
    </div>
</form>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
