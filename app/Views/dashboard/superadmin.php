<?php $title = 'Super Admin Dashboard'; $active = 'dashboard'; $v = $totals['volunteers']; $n = $totals['newmuslims']; $st = $totals['stats']; ?>
<div class="hero">
  <div><div class="eyebrow">Campaign control · <?= $year ?></div><h1>Super Admin Dashboard</h1><p class="muted" style="color:rgba(255,255,255,.7);margin:4px 0 0">Assalamu alaikum <?= e($u['first_name'] ?: 'Admin') ?>. Here is the whole organisation at a glance.</p></div>
  <span class="grow"></span>
  <a class="btn btn-accent" href="<?= e(url('/analytics')) ?>"><?= icon('bar-chart', '', 16) ?> Analytics</a>
  <a class="btn btn-light" href="<?= e(url('/admin/users?type=newmuslim')) ?>"><?= icon('users', '', 16) ?> New Muslims tracking</a>
  <a class="btn btn-light" href="<?= e(url('/admin/teams')) ?>"><?= icon('users', '', 16) ?> Teams</a>
</div>
<div class="grid-4">
  <div class="stat primary"><div class="stat-label">Active volunteers</div><div class="stat-value"><?= (int) ($v['approved'] ?? 0) ?></div><div class="stat-sub"><?= (int) $totals['in_review'] ?> in the approval pipeline · <?= (int) ($v['paused'] ?? 0) ?> paused</div></div>
  <div class="stat accent"><div class="stat-label">New Muslims</div><div class="stat-value"><?= (int) (($n['approved'] ?? 0) + ($n['completed'] ?? 0) + ($n['enrolled'] ?? 0)) ?></div><div class="stat-sub"><?= (int) $totals['unassigned'] ?> without a mentor · <?= (int) ($n['completed'] ?? 0) ?> completed</div></div>
  <div class="stat info"><div class="stat-label">Shahadas <?= $year ?></div><div class="stat-value"><?= (int) $st['shahada'] ?></div><div class="stat-sub"><?= (int) $st['chats_quality'] ?> quality chats · <?= (int) $st['chats'] ?> chats</div></div>
  <div class="stat"><div class="stat-label">Events <?= $year ?></div><div class="stat-value"><?= (int) $totals['events']['in_range'] ?></div><div class="stat-sub"><?= (int) $totals['events']['future'] ?> upcoming · <?= (int) $totals['teams'] ?> teams</div></div>
  <div class="stat"><div class="stat-label">Reports <?= $year ?></div><div class="stat-value"><?= (int) ($totals['event_reports']['submitted'] + $totals['mentor_reports']['submitted']) ?></div><div class="stat-sub"><?= (int) ($totals['event_reports']['pending'] + $totals['mentor_reports']['pending']) ?> pending</div></div>
  <div class="stat danger"><div class="stat-label">Needs attention</div><div class="stat-value"><?= (int) ($totals['pending_registrations'] + $totals['unassigned'] + $totals['in_review']) ?></div><div class="stat-sub"><a href="<?= e(url('/admin/users?type=pending')) ?>"><?= (int) $totals['pending_registrations'] ?> unfinished registrations</a> · <a href="<?= e(url('/admin/users?type=volunteer')) ?>"><?= (int) $totals['in_review'] ?> to review</a></div></div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head"><div><div class="eyebrow">Canada</div><h2>By province</h2></div><span class="grow"></span><span class="muted small">Based on each person's city or province</span></div>
  <?php if (!$provinces): ?><p class="muted">No people yet.</p><?php else: ?>
  <div class="grid-4">
    <?php foreach ($provinces as $p): ?>
    <div class="region-card">
      <div class="region-head"><span class="region-code"><?= e($p['code']) ?></span><div><div class="region-title"><?= e($p['name']) ?></div><div class="region-sub"><?= (int) ($p['volunteers'] + $p['newmuslims']) ?> people</div></div></div>
      <div class="region-stats"><div class="region-stat"><b><?= (int) $p['volunteers_active'] ?></b><span>Volunteers</span></div><div class="region-stat"><b><?= (int) $p['newmuslims_active'] ?></b><span>New Muslims</span></div></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head"><div><div class="eyebrow">Teams</div><h2>Team information</h2></div><span class="grow"></span><a class="btn btn-sm" href="<?= e(url('/admin/teams/new')) ?>"><?= icon('plus', '', 16) ?> New team</a></div>
  <?php if (!$teams): ?><p class="muted">No teams yet. Create the first team to start scheduling events.</p><?php else: ?>
  <div class="grid-3">
    <?php foreach ($teams as $t): ?>
    <div class="region-card">
      <div class="region-head"><span class="region-code"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $t['name']) ?: 'T', 0, 2))) ?></span><div><div class="region-title"><?= e($t['name']) ?></div><div class="region-sub"><?= e(start_case($t['parent'] ?? '')) ?> · <?= (int) $t['members'] ?> members · <?= (int) $t['leads'] ?> lead<?= $t['leads'] === 1 ? '' : 's' ?></div></div></div>
      <div class="region-stats">
        <div class="region-stat"><b><?= (int) $t['volunteers_active'] ?></b><span>Volunteers</span></div>
        <div class="region-stat"><b><?= (int) $t['newmuslims'] ?></b><span>New Muslims</span></div>
        <div class="region-stat"><b><?= (int) $t['shahada'] ?></b><span>Shahadas</span></div>
        <div class="region-stat"><b><?= (int) $t['reports_submitted'] ?></b><span>Reports</span></div>
        <div class="region-stat"><b><?= (int) $t['upcoming'] ?></b><span>Upcoming</span></div>
        <div class="region-stat"><b class="<?= $t['reports_pending'] ? 'no' : '' ?>"><?= (int) $t['reports_pending'] ?></b><span>Pending</span></div>
      </div>
      <div class="region-foot actions"><a class="btn btn-dark btn-sm" href="<?= e(url('/admin/teams/' . $t['id'])) ?>"><?= icon('external-link', '', 14) ?> Open team</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/events/planning/' . $t['id'])) ?>">Events</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/analytics?team=' . urlencode($t['id']))) ?>">Analytics</a></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="grid-2" style="margin-top:16px">
  <div class="card"><div class="card-head"><h2>Latest registrations</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/users?type=all')) ?>">All users</a></div>
    <?php if (!$recentUsers): ?><p class="muted">Nobody has registered yet.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>City</th><th>When</th></tr></thead><tbody>
    <?php foreach ($recentUsers as $r): ?><tr data-href="<?= e(url('/admin/users/' . $r['id'])) ?>" class="row-link"><td><?= e(\App\Models\User::fullName($r)) ?></td><td><?= e(ucfirst((string) $r['type'])) ?></td><td><span class="badge badge-<?= e($r['status']) ?>"><?= e(\App\Models\User::statusLabel($r)) ?></span></td><td><?= e($r['city']) ?></td><td><?= e(fmt_date($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Next 14 days</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/calendar')) ?>">Calendar</a></div>
    <?php if (!$upcoming): ?><p class="muted">No events scheduled in the next two weeks.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Event</th><th>Team</th></tr></thead><tbody>
    <?php foreach ($upcoming as $s): ?><tr data-href="<?= e(url('/attendance/' . $s['team_id'] . '/' . $s['id'])) ?>" class="row-link"><td><?= e(fmt_datetime($s['start_at'])) ?></td><td><?= e($s['name']) ?><br><small class="muted"><?= e($s['city']) ?></small></td><td><?= e($s['team_name']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
</div>
