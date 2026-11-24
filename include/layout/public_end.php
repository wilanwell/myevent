<?php /** Public layout: closing part. */ ?>
</main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Campus event management.</span>
        <span>
            <a href="<?= e(BASE_URL) ?>browse_events.php">Browse events</a>
            <?php if (!is_logged_in()): ?>&nbsp;&middot;&nbsp;<a href="<?= e(BASE_URL) ?>auth/register.php">Organise an event</a><?php endif; ?>
        </span>
    </div>
</footer>
<script src="<?= e(BASE_URL) ?>assets/js/app.js?v=5" defer></script>
</body>
</html>
