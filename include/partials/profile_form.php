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
$__options   = ['workshop' => 'Workshop', 'seminar' => 'Seminar', 'competition' => 'Competition',
                'festival' => 'Festival', 'sport' => 'Sport', 'course' => 'Course'];
?>
<form action="<?= e($form_action) ?>" method="POST" enctype="multipart/form-data" class="card card-narrow">
    <?= csrf_field() ?>

    <div class="card-body">
        <div class="form-section">
            <div class="profile-head">
                <label class="avatar-picker" for="profilePicInput" title="Change profile photo">
                    <img class="avatar avatar-lg" id="avatarPreview" src="<?= e($__avatar ?? '') ?>" alt="Profile photo"<?= $__avatar ? '' : ' hidden' ?>>
                    <span class="avatar avatar-lg" id="avatarPreview-placeholder" aria-hidden="true"<?= $__avatar ? ' hidden' : '' ?>><?= e(initials($user['name'])) ?></span>
                    <span class="avatar-picker-badge" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
                </label>
                <div>
                    <h2 class="card-title"><?= e($user['name']) ?></h2>
                    <p class="card-subtitle"><?= e($user['email']) ?> &middot; <?= e($user['category']) ?></p>
                    <p class="card-subtitle">Click the photo to upload a new one (JPG, PNG, GIF or WEBP, up to 2&nbsp;MB).</p>
                </div>
            </div>
            <input class="sr-only" type="file" name="profile_pic" id="profilePicInput" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#avatarPreview">
        </div>

        <div class="form-section">
            <h3 class="form-section-title">Personal details</h3>
            <p class="form-section-desc"><?= $edit_identity ? 'This is how you appear to participants and organisers.' : 'Your name, email and category are set at registration. Contact an administrator to change them.' ?></p>
            <div class="form-grid">
                <div class="field">
                    <label for="pf-name">Full name</label>
                    <?php if ($edit_identity): ?>
                        <input id="pf-name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="255" required>
                    <?php else: ?>
                        <input id="pf-name" type="text" value="<?= e($user['name']) ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label for="pf-email">Email</label>
                    <input id="pf-email" type="email" value="<?= e($user['email']) ?>" readonly>
                </div>
                <div class="field">
                    <label for="pf-phone">Phone number <span class="req" aria-hidden="true">*</span></label>
                    <input id="pf-phone" type="tel" name="phone" value="<?= e($user['phone']) ?>" pattern="[0-9+\- ]{7,20}" maxlength="20" autocomplete="tel" required>
                </div>
                <div class="field">
                    <label for="pf-org">Organisation or faculty</label>
                    <?php if ($edit_identity): ?>
                        <input id="pf-org" type="text" name="organization" value="<?= e($user['organization']) ?>" maxlength="255">
                    <?php else: ?>
                        <input id="pf-org" type="text" value="<?= e($user['organization']) ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="field span-2">
                    <label for="pf-bio">Bio</label>
                    <textarea id="pf-bio" name="bio" rows="3" maxlength="1000" placeholder="A sentence or two about you"><?= e($user['bio'] ?? '') ?></textarea>
                </div>
                <fieldset class="field span-2 fieldset-reset">
                    <legend class="label">Events you are interested in</legend>
                    <div class="choice-row">
                        <?php foreach ($__options as $__val => $__label): ?>
                            <label class="choice"><input type="checkbox" name="event[]" value="<?= e($__val) ?>"<?= in_array($__val, $__interests, true) ? ' checked' : '' ?>> <?= e($__label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="form-section" id="email-settings">
            <h3 class="form-section-title">Email notifications</h3>
            <p class="form-section-desc">You always see updates under the bell icon. Choose what we also send to <?= e($user['email']) ?>.</p>
            <input type="hidden" name="email_settings" value="1">
            <div class="toggle-list">
                <label class="toggle">
                    <input type="checkbox" name="email_participant" value="1"<?= (int) ($user['email_participant'] ?? 1) ? ' checked' : '' ?>>
                    <span><b>My registrations</b><span class="hint">Approvals, waitlist places, reminders the day before, and changes or cancellations of events I joined.</span></span>
                </label>
                <label class="toggle">
                    <input type="checkbox" name="email_organiser" value="1"<?= (int) ($user['email_organiser'] ?? 1) ? ' checked' : '' ?>>
                    <span><b>Events I organise</b><span class="hint">When someone registers or moves up from the waitlist.</span></span>
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">Change password</h3>
            <p class="form-section-desc">Leave both fields empty to keep your current password.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="pf-current">Current password</label>
                    <input id="pf-current" type="password" name="current_password" autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="pf-new">New password</label>
                    <input id="pf-new" type="password" name="new_password" minlength="8" autocomplete="new-password">
                    <span class="hint">At least 8 characters, including a letter and a number.</span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </div>
</form>
