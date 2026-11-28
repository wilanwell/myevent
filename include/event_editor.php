<?php
/**
 * MyEvent - Shared create/edit event screen (organisers and admins).
 *
 * The including page sets:
 *   $editor_event     ?array  Existing event row when editing, null when creating.
 *   $editor_redirect  string  Where to go after saving (path relative to BASE_URL).
 *   $editor_cancel    string  URL for the Cancel button.
 *   $page_title, $nav_active, $nav_area   Layout settings.
 *   $editor_pick_organiser bool  Admin screens: choose which user organises the event.
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
        'end_time'         => (string) ($_POST['end_time'] ?? ''),
        'mode'             => (string) ($_POST['mode'] ?? ''),
        'reg_close_date'   => (string) ($_POST['reg_close_date'] ?? ''),
        'max_participants' => max(0, (int) ($_POST['max_participants'] ?? 0)),
        'fee'              => parse_fee($_POST['fee'] ?? 0),
        'remarks'          => trim((string) ($_POST['remarks'] ?? '')),
        'visibility'       => (string) ($_POST['visibility'] ?? 'Public'),
    ];

    $errors = [];
    if ($d['event_name'] === '' || mb_strlen($d['event_name']) > 255) {
        $errors['event_name'] = __('Enter an event name (up to 255 characters).');
    }
    if ($d['venue'] === '' || mb_strlen($d['venue']) > 255) {
        $errors['venue'] = __('Enter where the event takes place.');
    }
    if (!valid_date($d['event_date'])) {
        $errors['event_date'] = __('Choose the event date.');
    }
    if (!valid_time($d['event_time'])) {
        $errors['event_time'] = __('Choose a start time.');
    }
    if ($d['end_time'] === '') {
        $d['end_time'] = null;
    } elseif (!valid_time($d['end_time'])) {
        $errors['end_time'] = __('Choose a valid end time.');
    } elseif (valid_time($d['event_time']) && substr($d['end_time'], 0, 5) <= substr($d['event_time'], 0, 5)) {
        $errors['end_time'] = __('The event must end after it starts.');
    }
    if (!in_array($d['mode'], ['Physical', 'Online', 'Hybrid'], true)) {
        $errors['mode'] = __('Choose physical, online or hybrid.');
    }
    if (!in_array($d['visibility'], ['Public', 'Private'], true)) {
        $errors['visibility'] = __('Choose who can find this event.');
    }
    if (!db_value($conn, 'SELECT 1 FROM event_category WHERE category_id = ?', 'i', [$d['category_id']])) {
        $errors['category_id'] = __('Choose a category.');
    }
    if ($d['reg_close_date'] === '') {
        $d['reg_close_date'] = $d['event_date'];
    } elseif (!valid_date($d['reg_close_date'])) {
        $errors['reg_close_date'] = __('Choose a valid closing date.');
    } elseif (valid_date($d['event_date']) && $d['reg_close_date'] > $d['event_date']) {
        $errors['reg_close_date'] = __('Registration must close on or before the event date.');
    }
    if ($d['contact_no'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $d['contact_no'])) {
        $errors['contact_no'] = __('Enter a phone number with 7 to 20 digits.');
    }
    return [$d, $errors];
}

$editing  = !empty($editor_event);
$data     = $editing ? $editor_event : [
    'event_name' => '', 'description' => '', 'category_id' => 0, 'contact_no' => '', 'venue' => '',
    'event_date' => '', 'event_time' => '', 'end_time' => null, 'mode' => 'Physical', 'reg_close_date' => '',
    'max_participants' => 0, 'fee' => 0, 'remarks' => '', 'visibility' => 'Public', 'poster_path' => null,
];
$errors   = [];
$pick_organiser = !empty($editor_pick_organiser);
$original_organiser = $editing && $editor_event['created_by'] !== null ? (int) $editor_event['created_by'] : null;
$organiser_id = $editing ? $original_organiser : current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$input, $errors] = event_input($conn);
    $data = array_merge($data, $input);
    if ($pick_organiser) {
        $picked = (int) ($_POST['organiser_id'] ?? 0);
        if ($picked === 0 && $original_organiser === null && $editing) {
            $organiser_id = null; // keep an event that has no organiser as it is
        } elseif ($picked > 0 && db_value($conn, 'SELECT 1 FROM users WHERE user_id = ?', 'i', [$picked])) {
            $organiser_id = $picked;
        } else {
            $errors['organiser_id'] = __('Choose who organises this event.');
        }
    }

    if (!$errors) {
        [$newPoster, $uploadError] = upload_image($_FILES['poster'] ?? null, POSTER_DIR, 'event');
        if ($uploadError) {
            $errors['poster'] = $uploadError;
        }
    }

    if (!$errors) {
        $poster = $newPoster ?? ($editing ? $editor_event['poster_path'] : null);
        $cols = [$data['event_name'], $data['description'], $data['category_id'], $data['contact_no'], $data['venue'],
                 $data['event_date'], $data['event_time'], $data['end_time'], $data['mode'], $data['reg_close_date'],
                 $data['max_participants'], $data['fee'], $data['remarks'], $data['visibility'], $poster];
        $types = 'ssisssssssidsss';

        if ($editing) {
            $stmt = $conn->prepare(
                'UPDATE events SET event_name = ?, description = ?, category_id = ?, contact_no = ?, venue = ?,
                        event_date = ?, event_time = ?, end_time = ?, mode = ?, reg_close_date = ?, max_participants = ?,
                        fee = ?, remarks = ?, visibility = ?, poster_path = ?, created_by = ?
                 WHERE event_id = ?');
            $cols[] = $organiser_id;
            $cols[] = (int) $editor_event['event_id'];
            $stmt->bind_param($types . 'ii', ...$cols);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO events (event_name, description, category_id, contact_no, venue, event_date, event_time, end_time,
                                     mode, reg_close_date, max_participants, fee, remarks, visibility, poster_path, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $cols[] = $organiser_id;
            $stmt->bind_param($types . 'i', ...$cols);
        }
        $stmt->execute();
        $savedId = $editing ? (int) $editor_event['event_id'] : (int) $conn->insert_id;
        $stmt->close();
        audit($conn, $editing ? 'event.update' : 'event.create', 'event', $savedId, $data['event_name']);
        if (is_admin()) {
            // Organisers hear about an administrator changing their event, or handing it to them.
            if ($editing) {
                notify_organiser_of_admin_change($conn, $original_organiser, $data['event_name'], 'edited');
            }
            if ($organiser_id !== $original_organiser) {
                notify_organiser_of_admin_change($conn, $organiser_id, $data['event_name'], 'assigned');
            }
        }
        if ($editing) {
            $told = notify_event_changed($conn, $editor_event, array_merge($data, ['event_id' => $savedId]));
            if ($told) {
                flash('info', _n('We let {n} registered person know about the change.', 'We let {n} registered people know about the change.', $told));
            }
            $moved = promote_waitlist($conn, $savedId);
            if ($moved) {
                flash('info', _n('{n} person moved up from the waitlist into the new places.', '{n} people moved up from the waitlist into the new places.', $moved));
            }
        }

        if ($newPoster && $editing) {
            delete_upload($editor_event['poster_path'], POSTER_DIR);
        }
        flash('success', $editing ? __('Event updated.') : __('Event published.'));
        redirect($editor_redirect);
    }
}

$categories = db_all($conn, 'SELECT category_id, categoryName FROM event_category ORDER BY categoryName');
$organisers = $pick_organiser ? db_all($conn, 'SELECT user_id, name, email, role FROM users ORDER BY name') : [];

/** Field error message, if any. */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<span class="hint field-error" id="err-' . e($field) . '">' . e($errors[$field]) . '</span>' : '';
};
$invalid = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
};

include ROOT_PATH . '/include/layout/app_start.php';
?>

<a class="back-link" href="<?= e($editor_cancel) ?>"><i class="fa-solid fa-arrow-left"></i> <?= t('Back') ?></a>
<div class="page-header card-narrow">
    <div>
        <h1><?= $editing ? t('Edit event') : t('Create an event') ?></h1>
        <p><?= $editing ? t('Changes are visible to participants straight away.') : t('Fill in the details participants need to decide and register.') ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error card-narrow" role="alert"><?= t('Please fix the highlighted fields and try again.') ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="card card-narrow" novalidate>
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-section">
            <h2 class="form-section-title"><?= t('About the event') ?></h2>
            <p class="form-section-desc"><?= t('The name and description appear on the event page.') ?></p>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="ev-name"><?= t('Event name') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-name" type="text" name="event_name" value="<?= e($data['event_name']) ?>" maxlength="255" required<?= $invalid('event_name') ?>>
                    <?= $err('event_name') ?>
                </div>
                <div class="field">
                    <label for="ev-cat"><?= t('Category') ?> <span class="req" aria-hidden="true">*</span></label>
                    <select id="ev-cat" name="category_id" required<?= $invalid('category_id') ?>>
                        <option value=""><?= t('Choose a category') ?></option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['category_id'] ?>"<?= (int) $data['category_id'] === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['categoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('category_id') ?>
                </div>
                <div class="field">
                    <label for="ev-vis"><?= t('Who can find it') ?></label>
                    <select id="ev-vis" name="visibility"<?= $invalid('visibility') ?>>
                        <option value="Public"<?= $data['visibility'] === 'Public' ? ' selected' : '' ?>><?= t('Public: listed for everyone') ?></option>
                        <option value="Private"<?= $data['visibility'] === 'Private' ? ' selected' : '' ?>><?= t('Private: only people with the link') ?></option>
                    </select>
                </div>
                <?php if ($pick_organiser): ?>
                    <div class="field span-2">
                        <label for="ev-org"><?= t('Organiser') ?> <span class="req" aria-hidden="true">*</span></label>
                        <select id="ev-org" name="organiser_id" required<?= $invalid('organiser_id') ?>>
                            <?php if ($editing && $original_organiser === null): ?>
                                <option value="0"<?= $organiser_id === null ? ' selected' : '' ?>><?= t('No organiser') ?></option>
                            <?php endif; ?>
                            <?php foreach ($organisers as $o): ?>
                                <option value="<?= (int) $o['user_id'] ?>"<?= $organiser_id === (int) $o['user_id'] ? ' selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['email']) ?>)<?= (int) $o['user_id'] === current_user_id() ? ' ' . t('(you)') : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint" id="ev-org-hint"><?= t('The organiser approves registrations, checks people in and gets the organiser emails.') ?></span>
                        <?= $err('organiser_id') ?>
                    </div>
                <?php endif; ?>
                <div class="field span-2">
                    <label for="ev-desc"><?= t('Description') ?></label>
                    <textarea id="ev-desc" name="description" rows="4"><?= e($data['description']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="form-section-title"><?= t('When and where') ?></h2>
            <div class="form-grid">
                <div class="field">
                    <label for="ev-date"><?= t('Date') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-date" type="date" name="event_date" value="<?= e($data['event_date']) ?>" required<?= $invalid('event_date') ?>>
                    <?= $err('event_date') ?>
                </div>
                <div class="field">
                    <label for="ev-time"><?= t('Start time') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-time" type="time" name="event_time" value="<?= e(substr((string) $data['event_time'], 0, 5)) ?>" required<?= $invalid('event_time') ?>>
                    <?= $err('event_time') ?>
                </div>
                <div class="field">
                    <label for="ev-end"><?= t('End time') ?></label>
                    <input id="ev-end" type="time" name="end_time" value="<?= e(substr((string) ($data['end_time'] ?? ''), 0, 5)) ?>"<?= $invalid('end_time') ?>>
                    <?= $err('end_time') ?: '<span class="hint">' . t('Optional. Calendars assume two hours if empty.') . '</span>' ?>
                </div>
                <div class="field">
                    <label for="ev-venue"><?= t('Venue') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="ev-venue" type="text" name="venue" value="<?= e($data['venue']) ?>" maxlength="255" placeholder="<?= t('e.g. Dewan Canselor or a meeting link') ?>" required<?= $invalid('venue') ?>>
                    <?= $err('venue') ?>
                </div>
                <div class="field">
                    <span class="label" id="ev-mode-label"><?= t('Mode') ?> <span class="req" aria-hidden="true">*</span></span>
                    <div class="choice-row" role="radiogroup" aria-labelledby="ev-mode-label">
                        <?php foreach (['Physical', 'Online', 'Hybrid'] as $m): ?>
                            <label class="choice"><input type="radio" name="mode" value="<?= $m ?>"<?= $data['mode'] === $m ? ' checked' : '' ?>> <?= e(status_label($m)) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('mode') ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="form-section-title"><?= t('Registration') ?></h2>
            <div class="form-grid">
                <div class="field">
                    <label for="ev-close"><?= t('Registration closes') ?></label>
                    <input id="ev-close" type="date" name="reg_close_date" value="<?= e($data['reg_close_date']) ?>"<?= $invalid('reg_close_date') ?>>
                    <?= $err('reg_close_date') ?: '<span class="hint">' . t('Leave empty to close on the event date.') . '</span>' ?>
                </div>
                <div class="field">
                    <label for="ev-max"><?= t('Places available') ?></label>
                    <input id="ev-max" type="number" name="max_participants" min="0" step="1" value="<?= (int) $data['max_participants'] ?: '' ?>" placeholder="<?= t('Unlimited') ?>">
                    <span class="hint"><?= t('Leave empty for no limit.') ?></span>
                </div>
                <div class="field">
                    <label for="ev-fee"><?= t('Fee (RM)') ?></label>
                    <input id="ev-fee" type="number" name="fee" min="0" step="0.01" value="<?= (float) $data['fee'] > 0 ? e(number_format((float) $data['fee'], 2, '.', '')) : '' ?>" placeholder="<?= t('Free') ?>">
                    <span class="hint"><?= t('Leave empty for a free event.') ?></span>
                </div>
                <div class="field">
                    <label for="ev-contact"><?= t('Contact number') ?></label>
                    <input id="ev-contact" type="tel" name="contact_no" value="<?= e($data['contact_no']) ?>" maxlength="20" placeholder="012-3456789"<?= $invalid('contact_no') ?>>
                    <?= $err('contact_no') ?>
                </div>
                <div class="field span-2">
                    <label for="ev-remarks"><?= t('Notes for participants') ?></label>
                    <textarea id="ev-remarks" name="remarks" rows="2" placeholder="<?= t('What to bring, dress code, parking…') ?>"><?= e($data['remarks']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="form-section-title"><?= t('Poster') ?></h2>
            <div class="poster-field">
                <img class="poster-preview" id="posterPreview" src="<?= e(poster_url($data['poster_path'] ?? null)) ?>" alt="<?= t('Poster preview') ?>">
                <div class="field">
                    <label for="ev-poster"><?= $editing ? t('Replace poster') : t('Upload a poster') ?></label>
                    <input id="ev-poster" type="file" name="poster" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#posterPreview"<?= $invalid('poster') ?>>
                    <?= $err('poster') ?: '<span class="hint">' . t('JPG, PNG, GIF or WEBP, up to 5 MB. Landscape 16:9 looks best.') . '</span>' ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="<?= e($editor_cancel) ?>"><?= t('Cancel') ?></a>
            <button type="submit" class="btn btn-primary"><?= $editing ? t('Save changes') : t('Publish event') ?></button>
        </div>
    </div>
</form>

<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
