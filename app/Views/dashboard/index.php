<?php $title = 'Home'; $active = 'dashboard'; ?>
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
<div class="grid-4">
  <div class="stat primary"><div class="stat-label">Pending event reports</div><div class="stat-value"><?= count($pendingEvent) ?></div><div class="stat-sub"><a href="<?= e(url('/reports/events/pending')) ?>">Submit now</a></div></div>
  <?php if (\App\Core\Auth::hasSubGroup($u, 'mentor')): ?><div class="stat accent"><div class="stat-label">Pending mentor reports</div><div class="stat-value"><?= count($pendingMentor) ?></div><div class="stat-sub"><a href="<?= e(url('/reports/mentor/pending')) ?>">Submit now</a></div></div><?php endif; ?>
  <div class="stat info"><div class="stat-label">Upcoming events (30 days)</div><div class="stat-value"><?= count($upcoming) ?></div><div class="stat-sub"><?= count($attending) ?> you are registered for</div></div>
  <div class="stat"><div class="stat-label">Reports submitted this month</div><div class="stat-value"><?= $submittedThisMonth ?></div><div class="stat-sub">JazakAllah khair</div></div>
</div>
<div class="grid-2" style="margin-top:16px">
  <div class="card">
    <div class="card-head"><h2>Upcoming events</h2><span class="grow"></span><a class="btn btn-sm" href="<?= e(url('/events/register')) ?>">Register</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/calendar')) ?>">Calendar</a></div>
    <?php if (!$upcoming): ?><p class="muted">No events scheduled for your teams in the next 30 days.</p><?php else: ?>
    <table class="table"><thead><tr><th>When</th><th>Event</th><th>Team</th><th></th></tr></thead><tbody>
    <?php foreach ($upcoming as $s): ?>
      <tr><td><?= e(fmt_datetime($s['start_at'])) ?></td><td><?= e($s['name']) ?><br><small class="muted"><?= e($s['city']) ?></small></td><td><?= e($s['team_name']) ?></td><td><?= in_array($s['id'], $attending, true) ? '<span class="badge badge-approved">Registered</span>' : '<a class="btn btn-sm" href="' . e(url('/events/register/' . $s['team_id'])) . '">Sign up</a>' ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </div>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>My teams</h2></div>
      <?php if (!$teams): ?><p class="muted">You are not in a team yet. A team lead will add you after approval.</p><?php else: ?>
      <ul class="check-list"><?php foreach ($teams as $t): ?><li><a href="<?= e(url('/teams/my/' . $t['id'])) ?>"><?= e($t['name']) ?></a><?= in_array($t['id'], $u['admin_teams'], true) ? '<span class="badge badge-approved">Lead</span>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php if ($pendingEvent || $pendingMentor): ?>
    <div class="card">
      <div class="card-head"><h2>Reports waiting for you</h2></div>
      <ul class="check-list">
        <?php foreach (array_slice($pendingEvent, 0, 5) as $r): ?><li><a href="<?= e(url('/reports/' . $r['id'])) ?>"><?= e($r['event_name']) ?> · <?= e(fmt_date($r['start_at'])) ?></a><span class="badge badge-pending">Event</span></li><?php endforeach; ?>
        <?php foreach (array_slice($pendingMentor, 0, 5) as $r): ?><li><a href="<?= e(url('/reports/' . $r['id'])) ?>"><?= e($r['mentee_name']) ?> · <?= e($r['period_key']) ?></a><span class="badge badge-pending">Mentor</span></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>
