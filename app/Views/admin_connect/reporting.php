<?php $title = 'Volunteer metrics & reporting'; $active = 'cc-reporting'; $h = fn(float $v) => rtrim(rtrim(number_format($v, 1), '0'), '.'); $riskBadge = ['high' => ['High risk', 'rejected'], 'medium' => ['At risk', 'pending'], 'low' => ['Active', 'ok']]; ?>
<div class="card-head" style="margin-bottom:10px">
  <div class="switch"><a href="<?= e(url('/admin/reporting?view=aggregate')) ?>" class="<?= $view === 'aggregate' ? 'is-active' : '' ?>">Aggregate</a><a href="<?= e(url('/admin/reporting')) ?>" class="<?= $view === 'volunteers' ? 'is-active' : '' ?>">Per volunteer</a></div>
  <span class="small muted">Activity, reliability and impact across the volunteer network.</span>
</div>
<?php if ($view === 'aggregate'): $a = $agg; $maxM = max(1, max(array_column($a['months'], 'hours'))); $maxT = max(1, max($a['byType'] ?: [1])); ?>
<div class="stat-row" style="margin-bottom:16px">
  <div class="stat plain"><div class="stat-label">Approved volunteers</div><div class="stat-value"><?= $a['approved'] ?></div></div>
  <div class="stat accent"><div class="stat-label">Total hours</div><div class="stat-value"><?= $h($a['hours']) ?></div></div>
  <div class="stat primary"><div class="stat-label">Active this month</div><div class="stat-value"><?= $a['activeMonth'] ?></div><div class="stat-sub">of <?= $a['approved'] ?> approved</div></div>
  <div class="stat <?= $a['dormant'] ? 'danger' : 'plain' ?>"><div class="stat-label">Dormant (30d+)</div><div class="stat-value"><?= count($a['dormant']) ?></div></div>
  <div class="stat info"><div class="stat-label">High risk</div><div class="stat-value"><?= $a['highRisk'] ?></div><div class="stat-sub">irregular or dormant</div></div>
</div>
<div class="two-col">
  <div class="card"><div class="card-head"><h2>Monthly hours trend</h2></div>
    <div style="display:flex;gap:10px;align-items:flex-end;height:180px"><?php foreach ($a['months'] as $m): ?><div style="flex:1;text-align:center"><div style="height:<?= (int) round($m['hours'] / $maxM * 140) ?>px;background:var(--primary);border-radius:6px 6px 0 0"></div><div class="small muted"><?= e($m['label']) ?><br><?= $h($m['hours']) ?>h</div></div><?php endforeach; ?></div>
  </div>
  <div class="card"><div class="card-head"><h2>Hours by activity type</h2></div>
    <?php if (!$a['byType']): ?><p class="empty">No reports yet.</p><?php else: foreach ($a['byType'] as $t => $v): ?><div class="small" style="margin-bottom:6px"><?= e($t) ?> · <?= $h($v) ?>h<div style="height:8px;border-radius:4px;background:<?= e($colors[$t] ?? '#94a3b8') ?>;width:<?= (int) round($v / $maxT * 100) ?>%"></div></div><?php endforeach; endif; ?>
  </div>
</div>
<div class="grid-3" style="margin-top:16px">
  <?php foreach ($a['categories'] as $c => $v): ?><div class="stat plain"><div class="stat-label"><?= e($c) ?></div><div class="stat-value"><?= $v['volunteers'] ?></div><div class="stat-sub"><?= $h($v['hours']) ?> hours · <?= $v['active'] ?> active this month</div></div><?php endforeach; ?>
</div>
<div class="card" style="margin-top:16px"><div class="card-head"><h2>Dormant volunteers</h2><span class="small muted">No activity in 30+ days</span></div>
  <?php if (!$a['dormant']): ?><p class="empty">Nobody is dormant. Alhamdulillah.</p><?php else: ?><div class="choices"><?php foreach ($a['dormant'] as $m): ?><a class="chip" href="<?= e(url('/admin/applications/' . $m['app']['id'])) ?>"><?= e($m['app']['name']) ?> · <?= $m['daysSinceActive'] >= 999 ? 'never' : $m['daysSinceActive'] . 'd ago' ?></a><?php endforeach; ?></div><?php endif; ?>
</div>
<?php else: $qs = fn(array $x) => '/admin/reporting?' . http_build_query(['q' => $q, 'status' => $status, 'category' => $category, 'risk' => $risk, 'sort' => $sort] + $x); ?>
<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(url('/admin/reporting')) ?>"><input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search volunteer…">
    <select name="status"><option value="" <?= $status === '' ? 'selected' : '' ?>>All statuses</option><?php foreach (\App\Models\Application::STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
    <select name="category"><option value="">All categories</option><?php foreach (['Global', 'Canada', 'Online'] as $c): ?><option <?= $category === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select>
    <select name="risk"><option value="">All risk levels</option><?php foreach ($riskBadge as $k => [$l]): ?><option value="<?= $k ?>" <?= $risk === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-dark">Filter</button><span class="small muted"><?= count($rows) ?> volunteer(s) shown</span>
  </form>
  <?php if (!$rows): ?><p class="empty">No volunteers match these filters.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Volunteer</th><th><a href="<?= e(url($qs(['sort' => 'xp']))) ?>">XP</a></th><th><a href="<?= e(url($qs(['sort' => 'hours']))) ?>">Hours</a></th><th><a href="<?= e(url($qs(['sort' => 'reports']))) ?>">Reports</a></th><th><a href="<?= e(url($qs(['sort' => 'consistency']))) ?>">Consistency</a></th><th>Training</th><th><a href="<?= e(url($qs(['sort' => 'lastActive']))) ?>">Last active</a></th><th>Risk</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $m): $a = $m['app']; [$rl, $rb] = $riskBadge[$m['risk']]; ?>
    <tr><td><strong><?= e($a['name']) ?></strong><br><span class="small muted"><?= e($a['volunteer_category']) ?> · <?= e($a['status']) ?></span></td>
      <td><span class="pill" style="border-color:<?= e($m['level_color']) ?>;color:<?= e($m['level_color']) ?>"><?= number_format($m['xp']) ?></span><?= $m['streak'] ? '<br><span class="small muted">' . $m['streak'] . 'w streak</span>' : '' ?></td>
      <td><?= $h($m['hours']) ?>h<br><span class="small muted"><?= $h($m['hoursThisMonth']) ?>h this month</span></td><td><?= $m['reports'] ?><br><span class="small muted"><?= $h($m['hoursThisWeek']) ?>h this week</span></td>
      <td><div class="progress-bar" style="width:90px"><span style="display:block;height:100%;border-radius:999px;width:<?= $m['consistency'] ?>%;background:<?= $m['consistency'] >= 60 ? 'var(--success)' : ($m['consistency'] >= 30 ? 'var(--accent)' : 'var(--danger)') ?>"></span></div><span class="small"><?= $m['consistency'] ?>%<?= $m['isBursty'] ? ' · irregular' : '' ?></span></td>
      <td><?= $m['completions'] ?>/<?= $m['modules'] ?><br><span class="small muted"><?= $m['completionRate'] ?>% done</span></td>
      <td class="small"><?= $m['lastActive'] ? e(fmt_date($m['lastActive'])) . '<br><span class="' . ($m['isDormant'] ? '' : 'muted') . '" style="' . ($m['isDormant'] ? 'color:var(--danger)' : '') . '">' . $m['daysSinceActive'] . 'd ago</span>' : '<span class="muted">Never</span>' ?></td>
      <td><span class="badge badge-<?= $rb ?>"><?= $rl ?></span></td><td><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/applications/' . $a['id'] . '?tab=activity')) ?>">View</a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
