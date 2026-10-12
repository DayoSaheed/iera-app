<?php $title = 'Event Registration · ' . $team['name']; $active = 'register'; $breadcrumbs = ['Event Registration' => '/events/register', $team['name'] => null]; ?>
<div class="card">
  <div class="card-head"><h2>Upcoming events</h2><span class="grow"></span>
    <?php if (count($teams) > 1): ?><select class="input" style="max-width:220px" onchange="location.href='<?= e(url('/events/register/')) ?>'+this.value"><?php foreach ($teams as $t): ?><option value="<?= e($t['id']) ?>" <?= $t['id'] === $team['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
  </div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="20" data-empty="No upcoming events for this team.">
    <thead><tr><th>Date</th><th>Event</th><th>Location</th><th data-filter>City</th><th>Min</th><th>Attending</th><th data-nosort></th></tr></thead>
    <tbody><?php foreach ($subs as $s): $att = $attendance[$s['id']] ?? []; $isMine = in_array($s['id'], $mine, true); ?>
      <tr class="<?= $isMine ? 'row-green' : '' ?>">
        <td data-value="<?= e($s['start_at']) ?>"><?= e(fmt_datetime($s['start_at'])) ?><br><small class="muted"><?= e($s['event_length']) ?> h</small></td><td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['description']) ?></td><td><?= e($s['city']) ?></td><td><?= (int) $s['min_participants'] ?></td>
        <td data-value="<?= count($att) ?>"><?php foreach ($att as $a): ?><span class="chip"><?= e($a['name']) ?></span><?php endforeach; ?></td>
        <td>
          <?php if ($isMine): ?>
            <form method="post" action="<?= e(url('/sub-events/' . $s['id'] . '/withdraw')) ?>" data-confirm="Cancel your registration for <?= e($s['name']) ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Cancel registration</button></form>
          <?php else: ?>
            <button type="button" class="btn btn-sm btn-primary" data-open="attend-<?= e($s['id']) ?>">Attend</button>
            <dialog class="modal" id="attend-<?= e($s['id']) ?>"><form method="post" action="<?= e(url('/sub-events/' . $s['id'] . '/attend')) ?>" class="modal-body"><?= csrf_field() ?>
              <h2><?= e($s['name']) ?></h2><p class="muted"><?= e(fmt_datetime($s['start_at'])) ?> · <?= e($s['description']) ?>, <?= e($s['city']) ?></p>
              <p><strong>Select the times you can attend</strong> (minimum stay 2 hours)</p>
              <div class="choices"><?php foreach (\App\Models\SubEvent::timeSlots($s) as $i => $slot): ?><label class="choice"><input type="checkbox" name="times[]" value="<?= e($slot) ?>" <?= $i === 0 ? 'checked' : '' ?>> <span><?= e($slot) ?></span></label><?php endforeach; ?></div>
              <div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="attend-<?= e($s['id']) ?>">Close</button><button class="btn btn-primary">Yes, I will attend</button></div>
            </form></dialog>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>
