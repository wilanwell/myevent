<?php
/**
 * Event card for public listings.
 * Expects $ev with: event_id, event_name, event_date, event_time, venue, mode, fee, poster_path, categoryName,
 *                   max_participants, taken (non-rejected registrations), reg_close_date.
 */
$__close  = $ev['reg_close_date'] ?: $ev['event_date'];
$__closed = $__close < date('Y-m-d');
$__full   = (int) $ev['max_participants'] > 0 && (int) $ev['taken'] >= (int) $ev['max_participants'];
?>
<article class="event-card">
    <div class="event-card-media">
        <img src="<?= e(poster_url($ev['poster_path'])) ?>" alt="" loading="lazy">
        <?= datestub($ev['event_date']) ?>
        <?php if ($__closed): ?>
            <span class="badge event-card-tag"><?= t('Registration closed') ?></span>
        <?php elseif ($__full): ?>
            <span class="badge badge-danger event-card-tag"><?= t('Full') ?></span>
        <?php endif; ?>
    </div>
    <div class="event-card-body">
        <span class="badge badge-brand no-dot badge-start"><?= e($ev['categoryName'] ?? __('General')) ?></span>
        <h3 class="event-card-title"><a href="<?= e(BASE_URL) ?>event_detail.php?id=<?= (int) $ev['event_id'] ?>"><?= e($ev['event_name']) ?></a></h3>
        <div class="event-card-meta">
            <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e(ldate('D, j M', $ev['event_date'])) ?>, <?= e(ldate('g:i A', $ev['event_time'])) ?></span>
            <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e($ev['venue']) ?> (<?= e(status_label($ev['mode'])) ?>)</span>
        </div>
        <div class="event-card-foot">
            <span class="event-card-fee"><?= e(format_fee($ev['fee'])) ?></span>
            <?php if ((int) $ev['max_participants'] > 0 && !$__full): ?>
                <span class="cell-muted"><?= e(_n('{n} place left', '{n} places left', (int) $ev['max_participants'] - (int) $ev['taken'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
</article>
