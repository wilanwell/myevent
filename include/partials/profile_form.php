<?php
/**
 * Shared profile form (participants and admins).
 *
 * Expects:
 *   $user           array   Row from users (name, email, phone, organization, bio, profile_pic, category, recommend_events)
 *   $form_action    string  URL of the handler
 *   $edit_identity  bool    Whether name and organisation can be changed (admins only)
 */
$__avatar    = avatar_url($user['profile_pic'] ?? null);
$__interests = array_filter(array_map('trim', explode(',', (string) ($user['recommend_events'] ?? ''))));
$__options   = ['workshop' => __('Workshop'), 'seminar' => __('Seminar'), 'competition' => __('Competition'),
                'festival' => __('Festival'), 'sport' => __('Sport'), 'course' => __('Course')];
?>
<form action="<?= e($form_action) ?>" method="POST" enctype="multipart/form-data" class="card card-narrow">
    <?= csrf_field() ?>

    <div class="card-body">
        <div class="form-section">
            <div class="profile-head">
                <label class="avatar-picker" for="profilePicInput" title="<?= t('Change profile photo') ?>">
                    <img class="avatar avatar-lg" id="avatarPreview" src="<?= e($__avatar ?? '') ?>" alt="<?= t('Profile photo') ?>"<?= $__avatar ? '' : ' hidden' ?>>
                    <span class="avatar avatar-lg" id="avatarPreview-placeholder" aria-hidden="true"<?= $__avatar ? ' hidden' : '' ?>><?= e(initials($user['name'])) ?></span>
                    <span class="avatar-picker-badge" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
                </label>
                <div>
                    <h2 class="card-title"><?= e($user['name']) ?></h2>
                    <p class="card-subtitle"><?= e($user['email']) ?> &middot; <?= e(status_label((string) $user['category'])) ?></p>
                    <p class="card-subtitle"><?= t('Click the photo to upload a new one (JPG, PNG, GIF or WEBP, up to 2 MB).') ?></p>
                </div>
            </div>
            <input class="sr-only" type="file" name="profile_pic" id="profilePicInput" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#avatarPreview">
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= t('Personal details') ?></h3>
            <p class="form-section-desc"><?= $edit_identity ? t('This is how you appear to participants and organisers.') : t('Your name, email and category are set at registration. Contact an administrator to change them.') ?></p>
            <div class="form-grid">
                <div class="field">
                    <label for="pf-name"><?= t('Full name') ?></label>
                    <?php if ($edit_identity): ?>
                        <input id="pf-name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="255" required>
                    <?php else: ?>
                        <input id="pf-name" type="text" value="<?= e($user['name']) ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label for="pf-email"><?= t('Email') ?></label>
                    <input id="pf-email" type="email" value="<?= e($user['email']) ?>" readonly>
                </div>
                <div class="field">
                    <label for="pf-phone"><?= t('Phone number') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="pf-phone" type="tel" name="phone" value="<?= e($user['phone']) ?>" pattern="[0-9+\- ]{7,20}" maxlength="20" autocomplete="tel" required>
                </div>
                <div class="field">
                    <label for="pf-org"><?= t('Organisation or faculty') ?></label>
                    <?php if ($edit_identity): ?>
                        <input id="pf-org" type="text" name="organization" value="<?= e($user['organization']) ?>" maxlength="255">
                    <?php else: ?>
                        <input id="pf-org" type="text" value="<?= e($user['organization']) ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="field span-2">
                    <label for="pf-bio"><?= t('Bio') ?></label>
                    <textarea id="pf-bio" name="bio" rows="3" maxlength="1000" placeholder="<?= t('A sentence or two about you') ?>"><?= e($user['bio'] ?? '') ?></textarea>
                </div>
                <fieldset class="field span-2 fieldset-reset">
                    <legend class="label"><?= t('Events you are interested in') ?></legend>
                    <div class="choice-row">
                        <?php foreach ($__options as $__val => $__label): ?>
                            <label class="choice"><input type="checkbox" name="event[]" value="<?= e($__val) ?>"<?= in_array($__val, $__interests, true) ? ' checked' : '' ?>> <?= e($__label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="form-section" id="email-settings">
            <h3 class="form-section-title"><?= t('Email notifications') ?></h3>
            <p class="form-section-desc"><?= t('You always see updates under the bell icon. Choose what we also send to {email}.', ['email' => $user['email']]) ?></p>
            <input type="hidden" name="email_settings" value="1">
            <div class="toggle-list">
                <label class="toggle">
                    <input type="checkbox" name="email_participant" value="1"<?= (int) ($user['email_participant'] ?? 1) ? ' checked' : '' ?>>
                    <span><b><?= t('My registrations') ?></b><span class="hint"><?= t('Approvals, waitlist places, reminders the day before, and changes or cancellations of events I joined.') ?></span></span>
                </label>
                <label class="toggle">
                    <input type="checkbox" name="email_organiser" value="1"<?= (int) ($user['email_organiser'] ?? 1) ? ' checked' : '' ?>>
                    <span><b><?= t('Events I organise') ?></b><span class="hint"><?= t('When someone registers or moves up from the waitlist.') ?></span></span>
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= t('Change password') ?></h3>
            <p class="form-section-desc"><?= t('Leave both fields empty to keep your current password.') ?></p>
            <div class="form-grid">
                <div class="field">
                    <label for="pf-current"><?= t('Current password') ?></label>
                    <input id="pf-current" type="password" name="current_password" autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="pf-new"><?= t('New password') ?></label>
                    <input id="pf-new" type="password" name="new_password" minlength="8" autocomplete="new-password">
                    <span class="hint"><?= t('At least 8 characters, including a letter and a number.') ?></span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= t('Save changes') ?></button>
        </div>
    </div>
</form>
