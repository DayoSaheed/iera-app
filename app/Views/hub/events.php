<?php use App\Models\Community; $title = 'Events'; $active = 'events'; ?>
<p class="muted" style="margin-top:-6px">Upcoming dawah events, training sessions & campaigns.</p>
<div class="choices" style="margin-bottom:14px"><a class="btn btn-sm <?= $type === '' ? 'btn-dark' : 'btn-ghost' ?>" href="<?= e(url('/events')) ?>">All Events</a><?php foreach (Community::EVENT_TYPES as $k => [$l, $ico]): ?><a class="btn btn-sm <?= $type === $k ? 'btn-dark' : 'btn-ghost' ?>" href="<?= e(url('/events?type=' . $k)) ?>"><?= icon($ico, '', 13) ?> <?= e($l) ?></a><?php endforeach; ?></div>
<?php $card = function (array $ev, bool $past) use ($u) { $in = in_array($u['id'], $ev['rsvp'], true); ?>
<div class="region-card event-card <?= $past ? 'is-past' : '' ?>">
  <?php if ($ev['cover_doc_id']): ?><img class="event-cover" src="<?= e(url('/documents/' . $ev['cover_doc_id'])) ?>" alt=""><?php else: ?><div class="event-cover is-blank"><?= icon(Community::EVENT_TYPES[$ev['event_type']][1] ?? 'calendar', '', 30) ?></div><?php endif; ?>
  <div style="padding:14px 16px"><span class="chip"><?= e(Community::EVENT_TYPES[$ev['event_type']][0] ?? $ev['event_type']) ?></span> <?php if ($ev['status'] === 'live'): ?><span class="badge badge-rejected">LIVE</span><?php endif; ?>
    <h3 style="margin:8px 0 4px"><?= e($ev['title']) ?></h3><p class="small muted"><?= e(mb_substr((string) $ev['description'], 0, 160)) ?></p>
    <div class="small"><?= icon('calendar', '', 12) ?> <?= e(fmt_date($ev['event_date'])) ?><?= $ev['event_time'] ? ' at ' . e($ev['event_time']) : '' ?><?php if ($ev['location']): ?><br><?= icon('map-pin', '', 12) ?> <?= e($ev['location']) ?><?php endif; ?><br><?= icon('users', '', 12) ?> <?= count($ev['rsvp']) ?> interested</div>
    <?php if (!$past): ?><form method="post" action="<?= e(url('/events/' . $ev['id'] . '/rsvp')) ?>" style="margin-top:10px"><?= csrf_field() ?><button class="btn btn-sm <?= $in ? 'btn-dark' : 'btn-primary' ?>"><?= $in ? icon('check', '', 12) . " I'm Interested" : 'Register Interest' ?></button></form><?php endif; ?></div>
</div>
<?php }; ?>
<h2>Upcoming</h2>
<?php if (!$upcoming): ?><div class="card"><p class="empty">No events yet. Check back soon for upcoming events.</p></div><?php else: ?><div class="grid-3"><?php foreach ($upcoming as $ev) { $card($ev, false); } ?></div><?php endif; ?>
<?php if ($past): ?><h2 style="margin-top:22px">Past Events</h2><div class="grid-3"><?php foreach ($past as $ev) { $card($ev, true); } ?></div><?php endif; ?>
