<?php
$title = 'Analytics'; $active = 'analytics';
$v = $data['volunteers']; $n = $data['newmuslims']; $ev = $data['events']; $es = $data['event_stats']; $er = $data['event_reports']; $mr = $data['mentor_reports']; $att = $data['attendance'];
$metricLabels = ['chats' => 'Chats', 'chats_quality' => 'Quality chats', 'shahada' => 'Shahadas', 'materials' => 'Materials', 'quran' => 'Quran copies'];
$months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$qs = fn(array $o) => '?' . http_build_query(array_merge(['team' => $teamId, 'year' => $year ?? 'all', 'month' => $month, 'metric' => $metric], $o));
?>
<div class="card">
  <form method="get" class="actions">
    <select name="team" class="input" style="max-width:220px" data-autosubmit><option value="">All teams</option><?php foreach ($teams as $t): ?><option value="<?= e($t['id']) ?>" <?= $teamId === $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
    <select name="year" class="input" style="max-width:140px" data-autosubmit><option value="all" <?= !$year ? 'selected' : '' ?>>All time</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select>
    <select name="month" class="input" style="max-width:140px" data-autosubmit <?= !$year ? 'disabled' : '' ?>><option value="">Whole year</option><?php foreach ($months as $i => $m): ?><option value="<?= $i ?>" <?= $month === $i ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select>
    <input type="hidden" name="metric" value="<?= e($metric) ?>">
    <span class="grow"></span>
    <a class="btn btn-sm" href="<?= e(url('/analytics/export.csv' . $qs([]))) ?>"><?= icon('download', '', 16) ?> Monthly report (CSV)</a>
  </form>
</div>
<div class="grid-4" style="margin-top:16px">
  <div class="stat primary"><div class="stat-label">Volunteers</div><div class="stat-value"><?= (int) ($v['approved'] ?? 0) ?></div><div class="stat-sub">In review <?= (int) ($v['in_review'] ?? 0) ?> · ID step <?= (int) (($v['enrolled'] ?? 0) + ($v['id_verified'] ?? 0)) ?> · Training <?= (int) ($v['training_video'] ?? 0) ?> · Paused <?= (int) ($v['paused'] ?? 0) ?></div></div>
  <div class="stat info"><div class="stat-label">New Muslims</div><div class="stat-value"><?= (int) ($n['approved'] ?? 0) ?></div><div class="stat-sub">Registered <?= (int) ($n['enrolled'] ?? 0) ?> · Completed <?= (int) ($n['completed'] ?? 0) ?> · Inactive <?= (int) ($n['inactive'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat-label">Events in period</div><div class="stat-value"><?= $ev['in_range'] ?></div><div class="stat-sub">This month <?= $ev['this_month'] ?> · Planned <?= $ev['future'] ?></div></div>
  <div class="stat"><div class="stat-label">Event attendance</div><div class="stat-value"><?= $att['yes'] ?></div><div class="stat-sub">Absent <?= $att['no'] ?></div></div>
  <div class="stat accent"><div class="stat-label">Quality chats</div><div class="stat-value"><?= (int) $es['chats_quality'] ?></div><div class="stat-sub">Chats <?= (int) $es['chats'] ?> · Materials <?= (int) $es['materials'] ?></div></div>
  <div class="stat accent"><div class="stat-label">Shahadas</div><div class="stat-value"><?= (int) $es['shahada'] ?></div><div class="stat-sub">Quran copies <?= (int) $es['quran'] ?> · Hours <?= $es['duration'] ?></div></div>
  <div class="stat"><div class="stat-label">Event reports</div><div class="stat-value"><?= $er['submitted'] ?></div><div class="stat-sub">Pending <?= $er['pending'] ?></div></div>
  <div class="stat"><div class="stat-label">Mentor reports</div><div class="stat-value"><?= $mr['submitted'] ?></div><div class="stat-sub">Pending <?= $mr['pending'] ?></div></div>
</div>
<div class="grid-2" style="margin-top:16px">
  <div class="card">
    <div class="card-head"><h2><?= e($metricLabels[$metric]) ?> by team</h2><span class="grow"></span>
      <div class="switch"><?php foreach ($metricLabels as $k => $l): ?><a class="<?= $metric === $k ? 'is-active' : '' ?>" href="<?= e($qs(['metric' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?></div></div>
    <?php $pt = $data['per_team']; $max = max(1, max(array_map(fn($t) => (float) $t['stats'][$metric], $pt) ?: [1])); $h = 24 * max(1, count($pt)) + 10; ?>
    <svg class="chart bar-chart" viewBox="0 0 600 <?= $h ?>" role="img" aria-label="Bar chart by team">
      <?php foreach ($pt as $i => $t): $val = (float) $t['stats'][$metric]; $w = round(380 * $val / $max); $y = $i * 24 + 4; ?>
        <a href="<?= e($qs(['team' => $t['id']])) ?>"><text x="0" y="<?= $y + 14 ?>" font-size="11" fill="currentColor"><?= e(\App\Core\Str::limit($t['name'], 26)) ?></text>
        <rect x="190" y="<?= $y ?>" width="<?= $w ?>" height="18" rx="4" fill="<?= $teamId === $t['id'] ? '#d97706' : '#0f766e' ?>"></rect>
        <text x="<?= 196 + $w ?>" y="<?= $y + 13 ?>" font-size="11" fill="currentColor"><?= (int) $val ?></text></a>
      <?php endforeach; ?>
    </svg>
  </div>
  <div class="card">
    <div class="card-head"><h2>Monthly trend <?= $year ? (int) $year : (int) date('Y') ?></h2><span class="grow"></span><span class="legend"><span style="--c:#0f766e">Events</span><span style="--c:#2563eb">Reports</span><span style="--c:#d97706">Shahadas</span></span></div>
    <?php $ms = $data['monthly']; $maxm = max(1, max(array_merge(array_column($ms, 'events'), array_column($ms, 'reports'), array_column($ms, 'shahada')))); ?>
    <svg class="chart" viewBox="0 0 600 220" role="img" aria-label="Monthly trend">
      <?php foreach ($ms as $i => $m): $x = 20 + $i * 48; foreach ([['events', '#0f766e', 0], ['reports', '#2563eb', 13], ['shahada', '#d97706', 26]] as [$k, $c, $off]): $bh = round(170 * $m[$k] / $maxm); ?>
        <rect x="<?= $x + $off ?>" y="<?= 190 - $bh ?>" width="11" height="<?= $bh ?>" fill="<?= $c ?>" rx="2"><title><?= e($m['month']) ?>: <?= $m[$k] ?> <?= $k ?></title></rect>
      <?php endforeach; ?><text x="<?= $x + 8 ?>" y="208" font-size="11" fill="currentColor"><?= e($m['month']) ?></text><?php endforeach; ?>
    </svg>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2>Per team</h2></div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-export="analytics_per_team.csv">
    <thead><tr><th>Team</th><th>Active volunteers</th><th>Active New Muslims</th><th>Events</th><th>Event reports</th><th>Mentor reports</th><th>Chats</th><th>Quality</th><th>Shahadas</th><th>Materials</th><th>Quran</th></tr></thead>
    <tbody><?php foreach ($data['per_team'] as $t): ?><tr data-href="<?= e($qs(['team' => $t['id']])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong></td><td><?= $t['volunteers_active'] ?></td><td><?= $t['newmuslims_active'] ?></td><td><?= $t['events'] ?></td><td><?= $t['event_reports']['submitted'] ?> <span class="muted">/ <?= $t['event_reports']['pending'] ?> pending</span></td><td><?= $t['mentor_reports']['submitted'] ?> <span class="muted">/ <?= $t['mentor_reports']['pending'] ?> pending</span></td><td><?= (int) $t['stats']['chats'] ?></td><td><?= (int) $t['stats']['chats_quality'] ?></td><td><?= (int) $t['stats']['shahada'] ?></td><td><?= (int) $t['stats']['materials'] ?></td><td><?= (int) $t['stats']['quran'] ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
