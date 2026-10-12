<?php $title = 'Participation log'; $active = 'cc-participation'; ?>
<div class="two-col">
  <div class="card">
    <div class="card-head"><h2>Logged participation</h2><span class="grow"></span>
      <form class="table-toolbar" style="margin:0" method="get" action="<?= e(url('/admin/participation-log')) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search volunteer or task…"><button class="btn btn-sm btn-dark">Search</button></form>
    </div>
    <?php if (!$rows): ?><p class="empty">Nothing logged yet. Use the form to record a volunteer's participation.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-empty="No records.">
      <thead><tr><th>Date</th><th>Volunteer</th><th data-filter>Task</th><th>Notes</th><th>Recorded by</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?><tr>
        <td data-value="<?= e($r['task_date']) ?>"><?= e(fmt_date($r['task_date'])) ?></td>
        <td><a href="<?= e(url('/admin/applications/' . $r['application_id'])) ?>"><strong><?= e($r['volunteer_name']) ?></strong></a><br><span class="small muted"><?= e($r['volunteer_email']) ?></span></td>
        <td><?= e($r['task']) ?></td><td class="small"><?= e($r['notes'] ?: '—') ?></td><td class="small"><?= e($r['recorded_by_name'] ?: $r['recorded_by_email']) ?></td>
        <td><form method="post" action="<?= e(url('/admin/participation-log')) ?>" data-confirm="Remove this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-ghost" title="Remove"><?= icon('trash', '', 14) ?></button></form></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Log participation</h2>
    <p class="small muted">Record an approved volunteer taking part in a task. Entries appear in their activity tab.</p>
    <form method="post" action="<?= e(url('/admin/participation-log')) ?>"><?= csrf_field() ?>
      <div class="field"><label>Volunteer</label><select name="application_id" required><option value="">Choose…</option><?php foreach ($apps as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['name']) ?> — <?= e($a['email']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Task</label><select name="task" data-reload-on-change="0"><?php foreach ($tasks as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>If "Other", describe the task</label><input name="task_other" placeholder="e.g. Iftar volunteering"></div>
      <div class="field"><label>Date</label><input type="date" name="task_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="field"><label>Notes (optional)</label><textarea name="notes" rows="3"></textarea></div>
      <button class="btn btn-primary">Save entry</button>
    </form>
  </div>
</div>
