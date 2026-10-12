<?php
$title = 'Dashboard'; $active = 'dashboard';
$v = $totals['volunteers']; $n = $totals['newmuslims']; $st = $totals['stats'];
$pendingReports = (int) ($totals['event_reports']['pending'] + $totals['mentor_reports']['pending']);
$todos = [
    ['Volunteer applications to review', 'Approve or reject people who finished registration', $totals['in_review'], '/admin/users?type=volunteer', 'urgent'],
    ['New Muslims without a mentor', 'Assign a mentor so follow-up can start', $totals['unassigned'], '/admin/users?type=newmuslim', 'urgent'],
    ['Organization applications', 'Masjids and partners waiting for approval', $totals['org_in_review'], '/admin/users?type=org', 'warn'],
    ['Vacation requests', 'Volunteers asking for a break', $totals['vacations_requested'], '/vacations', 'warn'],
    ['Reports overdue (7+ days)', 'Event or mentor reports still not submitted', $totals['reports_overdue'], '/analytics', 'warn'],
    ['Unfinished registrations', 'Signed in but never completed the form', $totals['pending_registrations'], '/admin/users?type=pending', ''],
];
$openTodos = array_filter($todos, fn($t) => $t[2] > 0);
?>
<div class="hero">
  <div><div class="eyebrow"><?= e(date('l, j F Y')) ?></div><h1>Assalamu alaikum, <?= e($u['first_name'] ?: 'Admin') ?></h1><p>Everything happening across <?= e(\App\Models\Setting::get('org_name') ?: 'the organisation') ?>, in the order you should look at it.</p></div>
  <span class="grow"></span>
  <a class="btn btn-accent" href="<?= e(url('/admin/users/new?type=volunteer')) ?>"><?= icon('user-plus', '', 16) ?> Add a person</a>
  <a class="btn btn-light" href="<?= e(url('/analytics')) ?>"><?= icon('bar-chart', '', 16) ?> Analytics</a>
</div>

<?php if (!empty($issues)): ?>
<section class="section" style="margin-top:0">
  <div class="section-head"><div class="section-title"><span class="section-num"><?= icon('wrench', '', 15) ?></span><div><h2>Things to fix</h2><p class="section-sub">Setup problems found on this server, most important first. Each one says where to fix it.</p></div></div><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/system')) ?>">System admin</a></div>
  <?= \App\Core\View::partial('partials/issues', ['issues' => $issues]) ?>
</section>
<?php endif; ?>
<section class="section" <?= empty($issues) ? 'style="margin-top:0"' : '' ?>>
  <div class="section-head"><div class="section-title"><span class="section-num">1</span><div><h2>Needs your attention</h2><p class="section-sub">Items waiting on an administrator. Click one to deal with it.</p></div></div></div>
  <?php if (!$openTodos): ?>
    <div class="all-clear"><?= icon('check-circle', '', 20) ?> Nothing is waiting on you right now.</div>
  <?php else: ?>
  <ul class="todo-list">
    <?php foreach ($todos as [$label, $sub, $count, $href, $tone]): ?>
    <li><a class="todo <?= $count ? 'is-' . ($tone ?: 'normal') : 'is-zero' ?>" href="<?= e(url($href)) ?>"><span class="todo-count"><?= (int) $count ?></span><span class="todo-text"><span class="todo-title"><?= e($label) ?></span><br><span class="todo-sub"><?= e($sub) ?></span></span><?= icon('chevron-right', '', 18) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">2</span><div><h2>The year so far (<?= $year ?>)</h2><p class="section-sub">Totals for the whole organisation. Open Analytics to filter by team or month.</p></div></div></div>
  <div class="stat-row">
    <div class="stat primary"><div class="stat-label">Active volunteers</div><div class="stat-value"><?= (int) ($v['approved'] ?? 0) ?></div><div class="stat-sub"><?= (int) ($v['paused'] ?? 0) ?> paused</div></div>
    <div class="stat accent"><div class="stat-label">New Muslims in care</div><div class="stat-value"><?= (int) (($n['approved'] ?? 0) + ($n['enrolled'] ?? 0)) ?></div><div class="stat-sub"><?= (int) ($n['completed'] ?? 0) ?> completed the program</div></div>
    <div class="stat info"><div class="stat-label">Shahadas</div><div class="stat-value"><?= (int) $st['shahada'] ?></div><div class="stat-sub">from <?= (int) $st['chats_quality'] ?> quality conversations</div></div>
    <div class="stat info"><div class="stat-label">Street dawah events</div><div class="stat-value"><?= (int) $totals['events']['in_range'] ?></div><div class="stat-sub"><?= (int) $totals['events']['future'] ?> still to come</div></div>
    <div class="stat <?= $pendingReports ? 'danger' : 'plain' ?>"><div class="stat-label">Reports</div><div class="stat-value"><?= (int) ($totals['event_reports']['submitted'] + $totals['mentor_reports']['submitted']) ?></div><div class="stat-sub"><?= $pendingReports ?> not yet submitted</div></div>
    <div class="stat plain"><div class="stat-label">Teams</div><div class="stat-value"><?= (int) $totals['teams'] ?></div><div class="stat-sub"><a href="<?= e(url('/admin/teams')) ?>">Manage teams</a></div></div>
  </div>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">3</span><div><h2>Across Canada</h2><p class="section-sub">Where volunteers and New Muslims are, by province. Based on each person's city.</p></div></div></div>
  <?php if (!$provinces): ?><div class="card"><p class="muted" style="margin:0">No people with a Canadian city yet.</p></div><?php else: ?>
  <div class="grid-4">
    <?php foreach ($provinces as $p): ?>
    <div class="region-card">
      <div class="region-head"><span class="region-code"><?= e($p['code']) ?></span><div><div class="region-title"><?= e($p['name']) ?></div><div class="region-sub"><?= (int) ($p['volunteers'] + $p['newmuslims']) ?> people in total</div></div></div>
      <div class="region-stats"><div class="region-stat"><b><?= (int) $p['volunteers_active'] ?></b><span>Volunteers</span></div><div class="region-stat"><b><?= (int) $p['newmuslims_active'] ?></b><span>New Muslims</span></div></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">4</span><div><h2>Teams</h2><p class="section-sub">One card per team. "Pending" is the number of reports the team still owes.</p></div></div><span class="grow"></span><a class="btn btn-sm" href="<?= e(url('/admin/teams/new')) ?>"><?= icon('plus', '', 16) ?> New team</a></div>
  <?php if (!$teams): ?><div class="card"><p class="muted" style="margin:0">No teams yet. Create the first team to start scheduling events.</p></div><?php else: ?>
  <div class="grid-3">
    <?php foreach ($teams as $t): ?>
    <div class="region-card">
      <div class="region-head"><span class="region-code"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $t['name']) ?: 'T', 0, 2))) ?></span><div><div class="region-title"><?= e($t['name']) ?></div><div class="region-sub"><?= e(start_case($t['parent'] ?? '')) ?> · <?= (int) $t['members'] ?> members · <?= (int) $t['leads'] ?> lead<?= $t['leads'] === 1 ? '' : 's' ?></div></div></div>
      <div class="region-stats">
        <div class="region-stat"><b><?= (int) $t['volunteers_active'] ?></b><span>Volunteers</span></div>
        <div class="region-stat"><b><?= (int) $t['newmuslims'] ?></b><span>New Muslims</span></div>
        <div class="region-stat"><b><?= (int) $t['shahada'] ?></b><span>Shahadas</span></div>
        <div class="region-stat"><b><?= (int) $t['upcoming'] ?></b><span>Upcoming</span></div>
        <div class="region-stat"><b><?= (int) $t['reports_submitted'] ?></b><span>Reports</span></div>
        <div class="region-stat"><b class="<?= $t['reports_pending'] ? 'no' : '' ?>"><?= (int) $t['reports_pending'] ?></b><span>Pending</span></div>
      </div>
      <div class="region-foot actions"><a class="btn btn-dark btn-sm" href="<?= e(url('/admin/teams/' . $t['id'])) ?>">Open team</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/events/planning/' . $t['id'])) ?>">Events</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/analytics?team=' . urlencode($t['id']))) ?>">Analytics</a></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">5</span><div><h2>Recent activity</h2><p class="section-sub">The newest people and the next two weeks of events.</p></div></div></div>
  <div class="two-col">
    <div class="card"><div class="card-head"><h2>Latest registrations</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/users?type=all')) ?>">All users</a></div>
      <?php if (!$recentUsers): ?><p class="muted">Nobody has registered yet.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>City</th><th>When</th></tr></thead><tbody>
      <?php foreach ($recentUsers as $r): ?><tr data-href="<?= e(url('/admin/users/' . $r['id'])) ?>" class="row-link"><td><strong><?= e(\App\Models\User::fullName($r)) ?></strong></td><td><?= e(['newmuslim' => 'New Muslim', 'org' => 'Organization'][$r['type']] ?? ucfirst((string) $r['type'])) ?></td><td><span class="badge badge-<?= e($r['status']) ?>"><?= e(\App\Models\User::statusLabel($r)) ?></span></td><td><?= e($r['city']) ?></td><td class="muted"><?= e(fmt_date($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
    <div class="card"><div class="card-head"><h2>Next 14 days</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/calendar')) ?>">Calendar</a></div>
      <?php if (!$upcoming): ?><p class="muted">No events scheduled in the next two weeks.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Event</th><th>Team</th></tr></thead><tbody>
      <?php foreach ($upcoming as $s): ?><tr data-href="<?= e(url('/attendance/' . $s['team_id'] . '/' . $s['id'])) ?>" class="row-link"><td class="muted"><?= e(fmt_datetime($s['start_at'])) ?></td><td><strong><?= e($s['name']) ?></strong><br><small class="muted"><?= e($s['city']) ?></small></td><td><?= e($s['team_name']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
  </div>
</section>
