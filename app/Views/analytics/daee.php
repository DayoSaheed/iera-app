<?php $title = "Da'ee Activity"; $active = 'daee'; ?>
<div class="card">
  <form method="get" class="actions">
    <label>From <input type="date" class="input" name="from" value="<?= e($from) ?>" style="max-width:170px"></label>
    <label>To <input type="date" class="input" name="to" value="<?= e($to) ?>" style="max-width:170px"></label>
    <select name="team" class="input" style="max-width:220px"><option value="">All my teams</option><?php foreach ($teams as $t): ?><option value="<?= e($t['id']) ?>" <?= $teamId === $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary btn-sm">Apply</button><span class="grow"></span>
    <a class="btn btn-sm" href="<?= e(url('/daee-activity/export.csv?from=' . urlencode($from) . '&to=' . urlencode($to) . '&team=' . urlencode((string) $teamId))) ?>">⬇ CSV</a>
  </form>
</div>
<div class="grid-3" style="margin-top:16px">
  <div class="stat primary"><div class="stat-label">Da'ees in view</div><div class="stat-value"><?= count($rows) ?></div></div>
  <div class="stat danger"><div class="stat-label">No activity in range</div><div class="stat-value"><?= $noActivity ?></div></div>
  <div class="stat accent"><div class="stat-label">Total hours logged</div><div class="stat-value"><?= round($hours, 1) ?></div></div>
</div>
<div class="card" style="margin-top:16px">
  <p class="muted small">Based on event registrations and submitted event reports (hours from the report when available, otherwise the event length).</p>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-empty="No approved da'ees found.">
    <thead><tr><th>First name</th><th>Last name</th><th>Email</th><th>Events attended</th><th>Total hours</th><th>Last active</th><th data-filter>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr class="<?= $r['no_activity'] ? 'row-red' : '' ?>"><td><?= e($r['first_name']) ?></td><td><?= e($r['last_name']) ?></td><td><?= e($r['email']) ?></td><td><?= $r['events_attended'] ?></td><td><?= $r['total_hours'] ?></td><td data-value="<?= e($r['last_active']) ?>"><?= e(fmt_date($r['last_active'])) ?></td><td><?= $r['no_activity'] ? '<span class="no">No activity in range</span>' : '<span class="yes">Active</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
