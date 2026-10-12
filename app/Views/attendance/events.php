<?php $title = 'Event Attendance · ' . $team['name']; $active = 'attendance'; $breadcrumbs = ['Event Attendance' => '/attendance', $team['name'] => null]; ?>
<div class="card">
  <div class="card-head"><h2><?= $past ? 'Past events' : 'Upcoming events' ?></h2><span class="grow"></span>
    <div class="switch"><a class="<?= $past ? 'is-active' : '' ?>" href="<?= e(url('/attendance/' . $team['id'])) ?>">Past</a><a class="<?= !$past ? 'is-active' : '' ?>" href="<?= e(url('/attendance/' . $team['id'] . '?upcoming=1')) ?>">Upcoming</a></div></div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="20" data-empty="No events.">
    <thead><tr><th>Date</th><th>Event</th><th>Location</th><th data-filter>City</th><th>Hours</th><th>Attended</th><th data-nosort></th></tr></thead>
    <tbody><?php foreach ($subs as $s): $att = $attendance[$s['id']] ?? []; ?>
      <tr data-href="<?= e(url('/attendance/' . $team['id'] . '/' . $s['id'])) ?>" class="row-link"><td data-value="<?= e($s['start_at']) ?>"><?= e(fmt_datetime($s['start_at'])) ?></td><td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['description']) ?></td><td><?= e($s['city']) ?></td><td><?= e($s['event_length']) ?></td><td data-value="<?= count($att) ?>"><?= count($att) ?></td><td><a class="btn btn-sm" href="<?= e(url('/attendance/' . $team['id'] . '/' . $s['id'])) ?>">Mark attendance</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
