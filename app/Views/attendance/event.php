<?php $title = $sub['name'] . ' · ' . fmt_datetime($sub['start_at']); $active = 'attendance'; $breadcrumbs = ['Event Attendance' => '/attendance', $team['name'] => '/attendance/' . $team['id'], $sub['name'] => null]; ?>
<div class="card">
  <div class="card-head"><h2>Who attended?</h2><span class="grow"></span><span class="muted small"><?= e($sub['description']) ?>, <?= e($sub['city']) ?> · <?= e($sub['event_length']) ?> h</span></div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="50" data-export="attendance_<?= e(date('Ymd', strtotime($sub['start_at']))) ?>.csv">
    <thead><tr><th>First name</th><th>Last name</th><th data-filter>Attendance</th><th>Times</th><th>Email</th><th>Phone</th><th data-filter>Gender</th><th data-nosort></th></tr></thead>
    <tbody><?php foreach ($rows as $m): ?>
      <tr class="<?= $m['attendance'] ? 'row-green' : '' ?>">
        <td><?= e($m['first_name']) ?></td><td><?= e($m['last_name']) ?></td>
        <td data-value="<?= $m['attendance'] ?>"><?= $m['attendance'] === 2 ? '<span class="yes">Yes (by lead)</span>' : ($m['attendance'] === 1 ? '<span class="yes">Yes</span>' : '<span class="muted">-</span>') ?></td>
        <td><?= e(implode(', ', $m['times'])) ?></td><td><?= e($m['email']) ?></td><td><?= e(\App\Models\User::phoneOf($m)) ?></td><td><?= e(ucfirst((string) $m['gender'])) ?></td>
        <td>
          <?php if ($m['attendance']): ?>
            <form method="post" action="<?= e(url('/attendance/' . $sub['id'] . '/unmark')) ?>" data-confirm="Remove <?= e($m['first_name']) ?> from attendance?"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($m['id']) ?>"><button class="btn btn-sm btn-ghost">Remove</button></form>
          <?php else: ?>
            <button type="button" class="btn btn-sm btn-primary" data-open="mark-<?= e($m['id']) ?>">Mark attended</button>
            <dialog class="modal" id="mark-<?= e($m['id']) ?>"><form method="post" action="<?= e(url('/attendance/' . $sub['id'] . '/mark')) ?>" class="modal-body"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($m['id']) ?>">
              <h2>Did <?= e($m['first_name']) ?> attend?</h2><p class="muted">When did they attend?</p>
              <div class="choices"><?php foreach ($slots as $i => $slot): ?><label class="choice"><input type="checkbox" name="times[]" value="<?= e($slot) ?>" <?= $i === 0 ? 'checked' : '' ?>> <span><?= e($slot) ?></span></label><?php endforeach; ?></div>
              <div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="mark-<?= e($m['id']) ?>">Cancel</button><button class="btn btn-primary">Yes, attended</button></div>
            </form></dialog>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>
