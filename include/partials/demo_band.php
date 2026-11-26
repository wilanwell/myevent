<?php
/** Thin "demo mode" band shown above every page while signed in on a demo site. */
if (!DEMO_MODE || !is_logged_in()) {
    return;
}
?>
<div class="demo-band" role="note">
    <i class="fa-solid fa-flask" aria-hidden="true"></i>
    <span><strong><?= t('Demo mode') ?></strong> <span class="demo-band-text"><?= t('You are exploring a demo. Changes are reset every night and no emails are sent.') ?></span></span>
</div>
