<?php
/**
 * MyEvent - Create an account
 */
require_once __DIR__ . '/../config.php';

if (is_logged_in()) {
    redirect('users/dashboard.php');
}

$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
$oldCategory = $old['category'] ?? 'Student';
$oldEvents   = $old['event'] ?? [];
$interests   = ['workshop' => 'Workshop', 'seminar' => 'Seminar', 'competition' => 'Competition',
                'festival' => 'Festival', 'sport' => 'Sport', 'course' => 'Course'];

$page_title  = 'Create an account';
$nav_current = 'register';
include ROOT_PATH . '/include/layout/public_start.php';
?>

<div class="auth">
    <section class="auth-aside">
        <h1>Join the campus calendar.</h1>
        <p>One account lets you register for events and publish your own. It is free for students, staff and the public.</p>
        <ul>
            <li><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Find workshops, talks and competitions</li>
            <li><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Publish events and take registrations</li>
            <li><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export participant lists in one click</li>
        </ul>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <h2>Create an account</h2>
            <p>It takes about a minute.</p>

            <?php include ROOT_PATH . '/include/flash.php'; ?>

            <form action="register_action.php" method="POST" class="form-grid">
                <?= csrf_field() ?>
                <div class="field span-2">
                    <label for="r-name">Full name <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-name" type="text" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="255" autocomplete="name" required>
                </div>
                <fieldset class="field span-2 fieldset-reset">
                    <legend class="label">I am a <span class="req" aria-hidden="true">*</span></legend>
                    <div class="choice-row">
                        <?php foreach (['Student', 'Staff', 'Public'] as $c): ?>
                            <label class="choice"><input type="radio" name="category" value="<?= $c ?>"<?= $oldCategory === $c ? ' checked' : '' ?> required> <?= $c === 'Public' ? 'Member of the public' : $c ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="field">
                    <label for="r-email">Email <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-email" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" maxlength="255" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="r-phone">Phone number</label>
                    <input id="r-phone" type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>" maxlength="20" pattern="[0-9+\- ]{7,20}" autocomplete="tel" placeholder="012-3456789">
                </div>
                <div class="field span-2">
                    <label for="r-org">Organisation or faculty <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-org" type="text" name="organization" value="<?= e($old['organization'] ?? '') ?>" maxlength="255" placeholder="e.g. Faculty of Computing" required>
                </div>
                <div class="field span-2">
                    <label for="r-pass">Password <span class="req" aria-hidden="true">*</span></label>
                    <input id="r-pass" type="password" name="password" minlength="8" autocomplete="new-password" required aria-describedby="r-pass-hint">
                    <span class="hint" id="r-pass-hint">At least 8 characters, including a letter and a number.</span>
                </div>
                <fieldset class="field span-2 fieldset-reset">
                    <legend class="label">Events you are interested in <span class="req" aria-hidden="true">*</span></legend>
                    <div class="choice-row">
                        <?php foreach ($interests as $val => $label): ?>
                            <label class="choice"><input type="checkbox" name="event[]" value="<?= $val ?>"<?= in_array($val, $oldEvents, true) ? ' checked' : '' ?>> <?= $label ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="span-2">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
                </div>
            </form>

            <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/include/layout/public_end.php'; ?>
