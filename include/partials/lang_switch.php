<?php
/** EN | BM language switcher (posts to lang.php and comes back to the same page). */
?>
<form class="lang-switch" action="<?= e(BASE_URL) ?>lang.php" method="POST" aria-label="<?= t('Language') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
    <?php foreach (['en' => 'EN', 'ms' => 'BM'] as $__code => $__short): $__on = current_locale() === $__code; ?>
        <button type="submit" name="locale" value="<?= e($__code) ?>" lang="<?= e($__code) ?>"
                class="lang-option<?= $__on ? ' is-active' : '' ?>" aria-pressed="<?= $__on ? 'true' : 'false' ?>"
                title="<?= e(LOCALES[$__code]) ?>"><?= e($__short) ?><span class="sr-only"> (<?= e(LOCALES[$__code]) ?>)</span></button>
    <?php endforeach; ?>
</form>
