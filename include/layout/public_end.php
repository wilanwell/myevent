<?php /** Public layout: closing part. */ ?>
</main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Welljoel | BI23110056.</span>
        <span>
            <a href="<?= e(BASE_URL) ?>browse_events.php"><?= t('Browse events') ?></a>
            <?php if (!is_logged_in()): ?>&nbsp;&middot;&nbsp;<a href="<?= e(BASE_URL) ?>auth/register.php"><?= t('Organise an event') ?></a><?php endif; ?>
        </span>
    </div>
</footer>
<script src="<?= e(BASE_URL) ?>assets/js/app.js?v=12" defer></script>
</body>
</html>
