<?php
$title = 'Interview calendar'; $active = 'cc-calendar';
$prev = (clone $first)->modify('-1 month')->format('Y-m');
$next = (clone $first)->modify('+1 month')->format('Y-m');
$start = (clone $first)->modify('-' . ((int) $first->format('N') - 1) . ' days');
$today = date('Y-m-d');
$daySlots = $day ? ($byDay[$day] ?? []) : [];
$monthAvail = 0; $monthBooked = 0;
foreach ($byDay as $list) { foreach ($list as $s) { $s['status'] === 'booked' ? $monthBooked++ : $monthAvail++; } }
?>
<div class="<?= $day ? 'two-col' : '' ?>">
  <div class="card">
    <div class="cal-head">
      <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/interview-calendar?month=' . $prev)) ?>"><?= icon('chevron-left', '', 16) ?></a>
      <h2 style="margin:0"><?= e($first->format('F Y')) ?></h2>
      <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/interview-calendar?month=' . $next)) ?>"><?= icon('chevron-right', '', 16) ?></a>
      <span class="grow"></span>
      <span class="small muted"><?= $monthAvail ?> available · <?= $monthBooked ?> booked</span>
      <a class="btn btn-sm" href="<?= e(url('/admin/interview-calendar?month=' . $month . '&day=' . ($day ?: ($month === date('Y-m') ? $today : $first->format('Y-m-d'))))) ?>"><?= icon('plus', '', 14) ?> Add slots</a>
    </div>
    <p class="small muted">Click a day to add or remove interview slots. Canadian applicants pick from available slots when they book their interview.</p>
    <div class="cal-grid">
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?><div class="cal-dow"><?= $dow ?></div><?php endforeach; ?>
      <?php $cur = clone $start; for ($i = 0; $i < 42; $i++): $ymd = $cur->format('Y-m-d'); $slots = $byDay[$ymd] ?? []; $avail = count(array_filter($slots, fn($s) => $s['status'] === 'available')); $booked = count($slots) - $avail; ?>
        <a class="cal-day <?= $cur->format('Y-m') !== $month ? 'is-other' : '' ?> <?= $ymd === $today ? 'is-today' : '' ?> <?= $slots ? 'has-slots' : '' ?> <?= $ymd === $day ? 'is-selected' : '' ?>" href="<?= e(url('/admin/interview-calendar?month=' . $month . '&day=' . $ymd)) ?>" style="text-decoration:none;color:inherit<?= $ymd === $day ? ';background:var(--accent-50)' : '' ?>">
          <div class="cal-daynum"><?= (int) $cur->format('j') ?></div>
          <?php if ($avail): ?><span class="cal-ev" style="background:#000"><?= $avail ?> free</span><?php endif; ?>
          <?php if ($booked): ?><span class="cal-ev" style="background:var(--primary)"><?= $booked ?> booked</span><?php endif; ?>
        </a>
      <?php $cur->modify('+1 day'); endfor; ?>
    </div>
  </div>
  <?php if ($day): ?>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2><?= e(date('l j F Y', strtotime($day))) ?></h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/interview-calendar?month=' . $month)) ?>">Close</a></div>
      <?php if (!$daySlots): ?><p class="muted small">No slots on this day yet.</p><?php else: ?>
      <ul class="check-list"><?php foreach ($daySlots as $s): ?>
        <li style="display:flex;gap:10px;align-items:center"><strong><?= e($s['slot_time']) ?></strong><span class="small muted"><?= (int) $s['duration_minutes'] ?> min</span>
          <?php if ($s['status'] === 'booked'): ?><span class="badge badge-pending">Booked</span><span class="small"><a href="<?= e(url('/admin/applications/' . $s['booked_application_id'])) ?>"><?= e($s['booked_by_name'] ?: $s['booked_by_email']) ?></a></span><?php else: ?><span class="badge badge-approved">Available</span><?php endif; ?>
          <span class="grow"></span>
          <?php if ($s['status'] !== 'booked'): ?><form method="post" action="<?= e(url('/admin/interview-calendar/slots/' . $s['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" title="Delete slot"><?= icon('trash', '', 14) ?></button></form><?php endif; ?>
        </li>
      <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Add slots</h2>
      <form method="post" action="<?= e(url('/admin/interview-calendar/slots')) ?>"><?= csrf_field() ?><input type="hidden" name="slot_date" value="<?= e($day) ?>">
        <div class="grid-3">
          <div class="field"><label>From</label><input type="time" name="start" value="10:00" required></div>
          <div class="field"><label>To</label><input type="time" name="end" value="12:00" required></div>
          <div class="field"><label>Slot length</label><select name="duration"><?php foreach ([15, 30, 45, 60] as $m): ?><option value="<?= $m ?>" <?= $defaultDuration === $m ? 'selected' : '' ?>><?= $m ?> min</option><?php endforeach; ?></select></div>
        </div>
        <button class="btn btn-primary btn-sm">Create slots</button>
        <span class="field-hint">Slots are generated back-to-back between the two times. Existing times are skipped.</span>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
