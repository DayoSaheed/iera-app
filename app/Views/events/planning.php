<?php
$title = 'Event Planning · ' . $team['name']; $active = 'planning';
$breadcrumbs = ['Event Planning' => '/events/planning', $team['name'] => null];
?>
<div class="card">
  <div class="card-head">
    <h2><?= $past ? 'Past occurrences' : 'Upcoming occurrences' ?></h2>
    <span class="grow"></span>
    <?php if (count($teams) > 1): ?><select class="input" style="max-width:220px" onchange="location.href='<?= e(url('/events/planning/')) ?>'+this.value"><?php foreach ($teams as $t): ?><option value="<?= e($t['id']) ?>" <?= $t['id'] === $team['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <div class="switch"><a class="<?= !$past ? 'is-active' : '' ?>" href="<?= e(url('/events/planning/' . $team['id'])) ?>">Upcoming</a><a class="<?= $past ? 'is-active' : '' ?>" href="<?= e(url('/events/planning/' . $team['id'] . '?past=1')) ?>">Past</a></div>
    <a class="btn btn-primary" href="<?= e(url('/events/new?team=' . urlencode($team['id']))) ?>">＋ New event</a>
  </div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="20" data-empty="No occurrences. Create an event to schedule some.">
    <thead><tr><th>Date</th><th>Event</th><th>Location</th><th data-filter>City</th><th>Hours</th><th>Min</th><th>Attendance</th><th data-nosort>Edit</th></tr></thead>
    <tbody><?php foreach ($subs as $s): $att = $attendance[$s['id']] ?? []; ?>
      <tr class="<?= count($att) < (int) $s['min_participants'] && !$past ? 'row-yellow' : '' ?>">
        <td data-value="<?= e($s['start_at']) ?>"><?= e(fmt_datetime($s['start_at'])) ?></td><td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['description']) ?></td><td><?= e($s['city']) ?></td><td><?= e($s['event_length']) ?></td><td><?= (int) $s['min_participants'] ?></td>
        <td data-value="<?= count($att) ?>"><?php foreach ($att as $a): ?><span class="chip" title="<?= e(implode(', ', $a['times'])) ?>"><?= e($a['name']) ?></span><?php endforeach; ?><?= $att ? '' : '<span class="muted">none</span>' ?></td>
        <td class="actions"><a class="btn btn-sm" href="<?= e(url('/sub-events/' . $s['id'] . '/edit')) ?>">Occurrence</a><?php if ($s['event_id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('/events/' . $s['event_id'] . '/edit')) ?>">Series</a><?php endif; ?><a class="btn btn-sm btn-ghost" href="<?= e(url('/attendance/' . $team['id'] . '/' . $s['id'])) ?>">Attendance</a></td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<div class="card">
  <div class="card-head"><h2>Event series</h2><span class="grow"></span><span class="muted small">Recurring definitions that generate the occurrences above</span></div>
  <?php if (!$series): ?><p class="muted">No event series yet.</p><?php else: ?>
  <table class="table"><thead><tr><th>Name</th><th>Type</th><th>Starts</th><th>Frequency</th><th>Open occurrences</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($series as $ev): ?><tr><td><strong><?= e($ev['name']) ?></strong><br><small class="muted"><?= e($ev['description']) ?></small></td><td><?= e(ucfirst($ev['event_type'])) ?></td><td><?= e(fmt_datetime($ev['start_at'])) ?></td><td><?= $ev['event_type'] === 'recurring' ? e(ucfirst($ev['recurrence'])) : '-' ?></td><td><?= $ev['event_type'] === 'recurring' ? (int) $ev['active_events'] : 1 ?></td><td><span class="badge badge-<?= e($ev['status']) ?>"><?= e($ev['status']) ?></span></td><td><a class="btn btn-sm" href="<?= e(url('/events/' . $ev['id'] . '/edit')) ?>">Edit</a></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</div>
