<?php
/**
 * Renders pending flash messages (set with flash()) and clears them.
 * Styles live in assets/css/app.css.
 */
$__flashes = get_flashes();
if ($__flashes): ?>
<?php foreach ($__flashes as $__f): ?>
    <div role="alert" class="flash flash-<?= e($__f['type']) ?>"><?= e($__f['message']) ?></div>
<?php endforeach; endif; unset($__flashes, $__f);
