<?php
/**
 * MyEvent - Create an account
 */
require_once __DIR__ . '/../../config.php';

if (is_logged_in()) {
    redirect('users/dashboard.php');
}

$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
$oldCategory = $old['category'] ?? 'Student';
$oldEvents   = $old['event'] ?? [];
$interests   = ['workshop' => __('Workshop'), 'seminar' => __('Seminar'), 'competition' => __('Competition'),
                'festival' => __('Festival'), 'sport' => __('Sport'), 'course' => __('Course')];
$categories  = ['Student' => __('Student'), 'Staff' => __('Staff'), 'Public' => __('Member of the public')];

$page_title  = __('Create an account');
$nav_current = 'register';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="auth">
    <section class="auth-aside">
        <div class="auth-aside-inner">
            <h1><?= t('Join the campus calendar.') ?></h1>
            <p><?= t('One account lets you register for events and publish your own. It is free for students, staff and the public.') ?></p>
            <ul>
                <li><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <?= t('Find workshops, talks and competitions') ?></li>
                <li><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> <?= t('Publish events and take registrations') ?></li>
                <li><i class="fa-solid fa-file-csv" aria-hidden="true"></i> <?= t('Export participant lists in one click') ?></li>
            </ul>
        </div>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <h2><?= t('Create an account') ?></h2>
            <p><?= t('It takes about a minute.') ?></p>

            <?php include ROOT_PATH . '/include/flash.php'; ?>

            <form action="register_action.php" method="POST" class="form-grid">
                <?= csrf_field() ?>
                <div class="field span-2">
                    <label for="r-name"><?= t('Full name') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-name" type="text" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="255" autocomplete="name" required>
                </div>
                <fieldset class="field span-2 fieldset-reset">
                    <legend class="label"><?= t('I am a') ?> <span class="req" aria-hidden="true">*</span></legend>
                    <div class="choice-row">
                        <?php foreach ($categories as $c => $label): ?>
                            <label class="choice"><input type="radio" name="category" value="<?= e($c) ?>"<?= $oldCategory === $c ? ' checked' : '' ?> required> <?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="field">
                    <label for="r-email"><?= t('Email') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-email" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" maxlength="255" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="r-phone"><?= t('Phone number') ?></label>
                    <input id="r-phone" type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>" maxlength="20" pattern="[0-9+\- ]{7,20}" autocomplete="tel" placeholder="012-3456789">
                </div>
                <div class="field span-2">
                    <label for="r-org"><?= t('Organisation or faculty') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-org" type="text" name="organization" value="<?= e($old['organization'] ?? '') ?>" maxlength="255" placeholder="<?= t('e.g. Faculty of Computing') ?>" required>
                </div>
                <div class="field span-2">
                    <label for="r-pass"><?= t('Password') ?> <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-pass" type="password" name="password" minlength="8" autocomplete="new-password" required aria-describedby="r-pass-hint">
                    <span class="hint" id="r-pass-hint"><?= t('At least 8 characters, including a letter and a number.') ?></span>
                </div>
                <fieldset class="field span-2 fieldset-reset">
                    <legend class="label"><?= t('Events you are interested in') ?> <span class="req" aria-hidden="true">*</span></legend>
                    <div class="choice-row">
                        <?php foreach ($interests as $val => $label): ?>
                            <label class="choice"><input type="checkbox" name="event[]" value="<?= e($val) ?>"<?= in_array($val, $oldEvents, true) ? ' checked' : '' ?>> <?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="span-2">
                    <button type="submit" class="btn btn-primary btn-block btn-lg"><?= t('Create account') ?></button>
                </div>
            </form>

            <p class="auth-switch"><?= t('Already have an account?') ?> <a href="login.php"><?= t('Log in') ?></a></p>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
