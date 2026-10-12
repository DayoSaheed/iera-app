<?php $title = 'Control centre'; $active = 'cc'; $s = $stats; $maxSign = max(1, max(array_column($weeks, 'signups'))); $maxHours = max(1, max(array_column($weeks, 'hours'))); ?>
<div class="hero">
  <div><span class="eyebrow">iERA Connect</span><h1>Control centre</h1><p>Everything that needs an admin's attention, in one place.</p></div>
  <span class="grow"></span>
  <div class="hero-stats"><div><b><?= $s['total'] ?></b><span>Applications</span></div><div><b><?= $s['approved'] ?></b><span>Approved</span></div><div><b><?= $s['new_week'] ?></b><span>New this week</span></div><div><b><?= rtrim(rtrim(number_format($s['hours'], 1), '0'), '.') ?></b><span>Hours logged</span></div></div>
</div>

<div class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">1</span><h2>Needs your attention</h2></div><span class="section-sub">Work through these first.</span></div>
  <ul class="todo-list">
    <li><a class="todo <?= $s['pending'] ? 'is-urgent' : 'is-zero' ?>" href="<?= e(url('/admin/applications?attention=1')) ?>"><span class="todo-count"><?= $s['pending'] ?></span><span class="todo-text"><span class="todo-title">Applications to review</span><br><span class="todo-sub">New or under review</span></span><?= icon('chevron-right', '', 18) ?></a></li>
    <li><a class="todo <?= $s['id_pending'] ? 'is-warn' : 'is-zero' ?>" href="<?= e(url('/admin/applications?status=New')) ?>"><span class="todo-count"><?= $s['id_pending'] ?></span><span class="todo-text"><span class="todo-title">ID documents to verify</span><br><span class="todo-sub">Uploaded, awaiting approval</span></span><?= icon('chevron-right', '', 18) ?></a></li>
    <li><a class="todo <?= $s['to_interview'] ? 'is-warn' : 'is-zero' ?>" href="<?= e(url('/admin/interviews')) ?>"><span class="todo-count"><?= $s['to_interview'] ?></span><span class="todo-text"><span class="todo-title">Interviews outstanding</span><br><span class="todo-sub">Not yet passed</span></span><?= icon('chevron-right', '', 18) ?></a></li>
    <li><a class="todo <?= $s['signup_requests'] ? 'is-urgent' : 'is-zero' ?>" href="<?= e(url('/admin/applications')) ?>"><span class="todo-count"><?= $s['signup_requests'] ?></span><span class="todo-text"><span class="todo-title">Admin access requests</span><br><span class="todo-sub">Waiting for a decision</span></span><?= icon('chevron-right', '', 18) ?></a></li>
  </ul>
</div>

<div class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">2</span><h2>Volunteers by category</h2></div></div>
  <div class="stat-row">
    <div class="stat primary"><div class="stat-label">Global</div><div class="stat-value"><?= $s['global'] ?></div><div class="stat-sub"><a href="<?= e(url('/admin/applications?category=Global')) ?>">Open list</a></div></div>
    <div class="stat accent"><div class="stat-label">UK</div><div class="stat-value"><?= $s['uk'] ?></div><div class="stat-sub"><a href="<?= e(url('/admin/applications?category=UK')) ?>">Open list</a></div></div>
    <div class="stat info"><div class="stat-label">Online</div><div class="stat-value"><?= $s['online'] ?></div><div class="stat-sub"><a href="<?= e(url('/admin/applications?category=Online')) ?>">Open list</a></div></div>
    <div class="stat plain"><div class="stat-label">Impact reports</div><div class="stat-value"><?= $s['reports'] ?></div><div class="stat-sub"><?= $s['completions'] ?> training completions · <?= $s['awards'] ?> awards</div></div>
    <div class="stat plain"><div class="stat-label">Community</div><div class="stat-value"><?= $s['posts'] ?></div><div class="stat-sub"><?= $s['events'] ?> events · <?= $s['stories'] ?> stories · <?= $s['feedback'] ?> feedback items</div></div>
  </div>
</div>

<div class="section two-col">
  <div class="card">
    <div class="card-head"><h2>Last 8 weeks</h2><span class="grow"></span><span class="small muted">Sign-ups and hours logged per week</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Week</th><th>Sign-ups</th><th></th><th>Hours</th><th></th></tr></thead>
      <tbody><?php foreach ($weeks as $w): ?><tr>
        <td><?= e($w['label']) ?></td>
        <td><?= $w['signups'] ?></td><td style="width:30%"><div style="height:8px;border-radius:4px;background:var(--primary);width:<?= (int) round($w['signups'] / $maxSign * 100) ?>%"></div></td>
        <td><?= rtrim(rtrim(number_format($w['hours'], 1), '0'), '.') ?></td><td style="width:30%"><div style="height:8px;border-radius:4px;background:var(--accent);width:<?= (int) round($w['hours'] / $maxHours * 100) ?>%"></div></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </div>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Top countries</h2></div>
      <?php if (!$countries): ?><p class="empty">No applications yet.</p><?php else: ?><dl class="kv"><?php foreach ($countries as $c => $n): ?><dt><?= e($c) ?></dt><dd><strong><?= $n ?></strong></dd><?php endforeach; ?></dl><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>Shortcuts</h2></div>
      <div class="actions">
        <a class="btn btn-sm" href="<?= e(url('/admin/applications')) ?>"><?= icon('clipboard', '', 14) ?> Applications</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/interview-calendar')) ?>"><?= icon('calendar', '', 14) ?> Interview calendar</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/risk-assessments')) ?>"><?= icon('shield', '', 14) ?> Risk assessments</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/hierarchy')) ?>"><?= icon('globe', '', 14) ?> Hierarchy</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/participation-log')) ?>"><?= icon('list', '', 14) ?> Participation log</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/applications/export.csv')) ?>"><?= icon('download', '', 14) ?> Export CSV</a>
      </div>
    </div>
  </div>
</div>

<?php if (\App\Core\Auth::isSysadmin($u ?? \App\Core\Auth::user())): ?>
<div class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">3</span><h2>Things to fix</h2></div><span class="section-sub">System setup checks (system admins only).</span></div>
  <div class="card"><?= \App\Core\View::partial('partials/issues', ['issues' => $issues]) ?></div>
</div>
<?php endif; ?>
