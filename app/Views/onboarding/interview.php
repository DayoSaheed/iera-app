<?php use App\Models\Application; $title = 'Book your interview'; $active = 'interview'; ?>
<?php if (!$app): ?>
<div class="card"><p>No application found.</p><a class="btn btn-primary" href="<?= e(url('/apply')) ?>">Apply to volunteer</a></div>
<?php elseif (!Application::isCanada($app)): ?>
<div class="card"><p>Interview booking is only available for Canadian volunteer applicants.</p><a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a></div>
<?php elseif ($booked && $app['interview_status'] === 'scheduled'): ?>
<div class="card" style="max-width:640px">
  <div class="all-clear" style="margin-bottom:14px"><?= icon('check-circle', '', 20) ?> Your interview is booked!</div>
  <p class="muted">We're looking forward to speaking with you. A member of the volunteer onboarding team will phone you at the time you've chosen.</p>
  <dl class="kv"><dt>Date</dt><dd><?= e(fmt_date($booked['slot_date'], 'l, j F Y')) ?></dd><dt>Time</dt><dd><?= e($booked['slot_time']) ?> (<?= (int) $booked['duration_minutes'] ?> min)</dd><dt>Interviewer</dt><dd><?= e($app['interviewer_name'] ?: $interviewer) ?></dd></dl>
  <p class="small muted" style="margin-top:12px">A confirmation has been saved to your application. Our team will be in touch if anything changes.</p>
  <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a>
</div>
<?php else: ?>
<?php $prev = (clone $first)->modify('-1 month'); $next = (clone $first)->modify('+1 month'); $dow = ((int) $first->format('N')) - 1; $days = (int) $first->format('t'); ?>
<div class="two-col">
  <div class="card">
    <p class="muted">Pick a date and time that works for you. This is a short phone interview — a member of the volunteer onboarding team will phone you at the time you choose.</p>
    <div class="cal-head"><a class="btn btn-sm btn-ghost" href="<?= e(url('/book-interview?month=' . $prev->format('Y-m'))) ?>"><?= icon('chevron-left', '', 14) ?></a><strong><?= e($first->format('F Y')) ?></strong><a class="btn btn-sm btn-ghost" href="<?= e(url('/book-interview?month=' . $next->format('Y-m'))) ?>"><?= icon('chevron-right', '', 14) ?></a></div>
    <div class="cal-grid">
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="cal-dow"><?= $d ?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $dow; $i++): ?><div class="cal-day is-other"></div><?php endfor; ?>
      <?php for ($d = 1; $d <= $days; $d++): $date = $first->format('Y-m-') . str_pad((string) $d, 2, '0', STR_PAD_LEFT); $n = count($byDay[$date] ?? []); ?>
      <?php if ($n): ?><a class="cal-day has-slots <?= $day === $date ? 'is-today' : '' ?>" href="<?= e(url('/book-interview?month=' . $month . '&day=' . $date)) ?>"><div class="cal-daynum"><?= $d ?></div><span class="badge badge-ok"><?= $n ?> slot<?= $n > 1 ? 's' : '' ?></span></a><?php else: ?><div class="cal-day <?= $date < date('Y-m-d') ? 'is-other' : '' ?>"><div class="cal-daynum"><?= $d ?></div></div><?php endif; ?>
      <?php endfor; ?>
    </div>
  </div>
  <div class="card-stack">
    <div class="card">
      <h2><?= $day ? e(fmt_date($day, 'l, j F')) : 'Pick a day' ?></h2>
      <?php if (!$day): ?><p class="muted">Days with available times are marked on the calendar.</p>
      <?php elseif (empty($byDay[$day])): ?><p class="muted">No available slots on this day.</p>
      <?php else: ?><div class="choices"><?php foreach ($byDay[$day] as $s): ?><form method="post" action="<?= e(url('/book-interview')) ?>"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= e($s['id']) ?>"><button class="btn btn-dark"><?= e($s['slot_time']) ?> <small>(<?= (int) $s['duration_minutes'] ?> min)</small></button></form><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="card">
      <form method="post" action="<?= e(url('/book-interview/alternative')) ?>" data-alt-form><?= csrf_field() ?>
        <label class="check"><input type="checkbox" data-alt-check><span>I can't find a suitable day or time from the available slots.</span></label>
        <div data-alt-body hidden style="margin-top:10px"><p class="small muted">Let our team know and we'll contact you to arrange an alternative appointment.</p><button class="btn btn-ghost">Request an alternative appointment</button></div>
      </form>
    </div>
  </div>
</div>
<?php $scripts = '<script>document.addEventListener("DOMContentLoaded",()=>{const c=document.querySelector("[data-alt-check]"),b=document.querySelector("[data-alt-body]");if(c&&b)c.addEventListener("change",()=>b.hidden=!c.checked);});</script>'; ?>
<?php endif; ?>
