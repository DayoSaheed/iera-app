<?php $title = 'Home'; $active = 'dashboard'; $isMentor = \App\Core\Auth::hasSubGroup($u, 'mentor'); $todoCount = count($pendingEvent) + count($pendingMentor); ?>
<?php if ($showWelcomeBack): ?>
<dialog class="modal" id="welcomeBack" data-autoopen>
  <form method="post" action="<?= e(url('/vacations')) ?>" class="modal-body">
    <?= csrf_field() ?>
    <h2>Welcome back!</h2>
    <p>Your account was paused after a period without signing in and has been reactivated automatically. If this was a planned break, you can let your team lead know below.</p>
    <div class="field"><label>Reason (optional)</label><textarea name="reason" rows="2"></textarea></div>
    <div class="grid-2"><div class="field"><label>From</label><input type="date" name="from_date" value="<?= date('Y-m-d', strtotime('-30 days')) ?>"></div><div class="field"><label>To</label><input type="date" name="to_date" value="<?= date('Y-m-d') ?>"></div></div>
    <div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="welcomeBack">Dismiss</button><button class="btn btn-primary">Log a vacation reason</button></div>
  </form>
</dialog>
<?php endif; ?>
<div class="hero">
  <div><div class="eyebrow"><?= e(date('l, j F Y')) ?></div><h1>Assalamu alaikum, <?= e($u['first_name'] ?: 'there') ?></h1><p><?= $todoCount ? "You have $todoCount report" . ($todoCount > 1 ? 's' : '') . ' to submit. Start with the list below.' : 'You are all caught up. JazakAllah khair for your work.' ?></p></div>
  <span class="grow"></span>
  <a class="btn btn-accent" href="<?= e(url('/events/register')) ?>"><?= icon('calendar', '', 16) ?> Register for an event</a>
  <?php if (!\App\Core\Auth::hasGroup($u, 'newmuslim')): ?><button type="button" class="btn btn-light" data-open="referModal"><?= icon('user-plus', '', 16) ?> Refer a New Muslim</button><?php endif; ?>
</div>

<section class="section" style="margin-top:0">
  <div class="section-head"><div class="section-title"><span class="section-num">1</span><div><h2>Your to-do list</h2><p class="section-sub">Reports are due after each event you attended and, for mentors, every week for each mentee.</p></div></div></div>
  <?php if (!$todoCount): ?>
    <div class="all-clear"><?= icon('check-circle', '', 20) ?> No reports waiting for you.</div>
  <?php else: ?>
  <ul class="todo-list">
    <?php foreach (array_slice($pendingEvent, 0, 6) as $r): ?>
    <li><a class="todo is-urgent" href="<?= e(url('/reports/' . $r['id'])) ?>"><span class="todo-count"><?= icon('clipboard', '', 22) ?></span><span class="todo-text"><span class="todo-title"><?= e($r['event_name']) ?></span><br><span class="todo-sub">Event report · <?= e(fmt_date($r['start_at'])) ?></span></span><?= icon('chevron-right', '', 18) ?></a></li>
    <?php endforeach; ?>
    <?php foreach (array_slice($pendingMentor, 0, 6) as $r): ?>
    <li><a class="todo is-warn" href="<?= e(url('/reports/' . $r['id'])) ?>"><span class="todo-count"><?= icon('heart-handshake', '', 22) ?></span><span class="todo-text"><span class="todo-title"><?= e($r['mentee_name']) ?></span><br><span class="todo-sub">Mentor report · <?= e($r['period_key']) ?></span></span><?= icon('chevron-right', '', 18) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($todoCount > 12): ?><p class="small muted" style="margin-top:8px">Showing the first 12. <a href="<?= e(url('/reports/events/pending')) ?>">All event reports</a><?= $isMentor ? ' · <a href="' . e(url('/reports/mentor/pending')) . '">All mentor reports</a>' : '' ?></p><?php endif; ?>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">2</span><div><h2>Your numbers</h2><p class="section-sub">A quick view of your activity.</p></div></div></div>
  <div class="stat-row">
    <div class="stat <?= $pendingEvent ? 'primary' : 'plain' ?>"><div class="stat-label">Event reports to submit</div><div class="stat-value"><?= count($pendingEvent) ?></div><div class="stat-sub"><a href="<?= e(url('/reports/events/pending')) ?>">Open list</a></div></div>
    <?php if ($isMentor): ?><div class="stat <?= $pendingMentor ? 'accent' : 'plain' ?>"><div class="stat-label">Mentor reports to submit</div><div class="stat-value"><?= count($pendingMentor) ?></div><div class="stat-sub"><a href="<?= e(url('/reports/mentor/pending')) ?>">Open list</a></div></div><?php endif; ?>
    <div class="stat info"><div class="stat-label">Events in the next 30 days</div><div class="stat-value"><?= count($upcoming) ?></div><div class="stat-sub"><?= count($attending) ?> you are registered for</div></div>
    <div class="stat plain"><div class="stat-label">Reports submitted this month</div><div class="stat-value"><?= $submittedThisMonth ?></div><div class="stat-sub">JazakAllah khair</div></div>
  </div>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">3</span><div><h2>Upcoming events and your teams</h2><p class="section-sub">Events for the teams you belong to. Sign up to let the lead know you are coming.</p></div></div></div>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><h2>Upcoming events</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/calendar')) ?>">Calendar</a></div>
      <?php if (!$upcoming): ?><p class="muted">No events scheduled for your teams in the next 30 days.</p><?php else: ?>
      <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Event</th><th>Team</th><th></th></tr></thead><tbody>
      <?php foreach ($upcoming as $s): ?>
        <tr><td class="muted"><?= e(fmt_datetime($s['start_at'])) ?></td><td><strong><?= e($s['name']) ?></strong><br><small class="muted"><?= e($s['city']) ?></small></td><td><?= e($s['team_name']) ?></td><td><?= in_array($s['id'], $attending, true) ? '<span class="badge badge-approved">Registered</span>' : '<a class="btn btn-sm" href="' . e(url('/events/register/' . $s['team_id'])) . '">Sign up</a>' ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>My teams</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/teams/my')) ?>">Details</a></div>
      <?php if (!$teams): ?><p class="muted">You are not in a team yet. A team lead will add you after approval.</p><?php else: ?>
      <ul class="check-list"><?php foreach ($teams as $t): ?><li><a href="<?= e(url('/teams/my/' . $t['id'])) ?>"><strong><?= e($t['name']) ?></strong></a><?= in_array($t['id'], $u['admin_teams'], true) ? '<span class="badge badge-approved">You lead this team</span>' : '<span class="badge badge-default">Member</span>' ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
</section>
