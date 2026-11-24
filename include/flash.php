<?php
/**
 * Renders pending flash messages (set with flash()) and clears them.
 * Self-contained styles so it works on pages with or without style.css.
 */
$__flashes = get_flashes();
if ($__flashes): ?>
<style>
    .flash { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; line-height: 1.5; }
    .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .flash-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .flash-info    { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
</style>
<?php foreach ($__flashes as $__f): ?>
    <div role="alert" class="flash flash-<?= e($__f['type']) ?>"><?= e($__f['message']) ?></div>
<?php endforeach; endif; unset($__flashes, $__f);
