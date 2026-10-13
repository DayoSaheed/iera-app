<?php $title = 'Admin volunteers'; $active = 'cc-adminvol'; ?>
<div class="card">
  <div class="card-head"><h2>Volunteers approved for the Administration role</h2><span class="grow"></span>
    <form class="table-toolbar" style="margin:0" method="get" action="<?= e(url('/admin/admin-volunteers')) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name or email…"><button class="btn btn-sm btn-dark">Search</button></form>
  </div>
  <p class="small muted">Volunteers appear here automatically once the Administration role (or its certificate) is approved on their application. Keep track of their task preferences, projects and history.</p>
  <?php if (!$records): ?><p class="empty">No admin volunteers yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Added</th><th>Volunteer</th><th>Task preference</th><th>Projects</th><th>Skills</th><th>Notes</th><th></th></tr></thead>
    <tbody><?php foreach ($records as $r): $a = $apps[$r['application_id']]; ?>
      <tr>
        <td><?= e(fmt_date($r['date_added_to_page'])) ?></td>
        <td><a href="<?= e(url('/admin/applications/' . $a['id'])) ?>"><strong><?= e($a['name']) ?></strong></a><br><span class="small muted"><?= e($a['email']) ?><?= $a['phone'] ? ' · ' . e($a['phone']) : '' ?></span></td>
        <td class="small"><?= e($r['task_pref'] ?: '—') ?></td><td class="small"><?= e($r['projects'] ?: '—') ?></td><td class="small"><?= e($r['skills'] ?: implode(', ', $a['skills'])) ?></td><td class="small"><?= e($r['notes'] ?: '—') ?></td>
        <td><a class="btn btn-sm" href="<?= e(url('/admin/admin-volunteers?open=' . $r['id'] . ($q ? '&q=' . urlencode($q) : ''))) ?>">Edit</a></td>
      </tr>
      <?php if ($open === $r['id']): ?>
      <tr><td colspan="7" style="background:var(--surface-2)">
        <form method="post" action="<?= e(url('/admin/admin-volunteers/' . $r['id'])) ?>"><?= csrf_field() ?>
          <div class="grid-3">
            <div class="field"><label>Date added</label><input type="date" name="date_added_to_page" value="<?= e($r['date_added_to_page']) ?>"></div>
            <div class="field"><label>Task preference</label><input name="task_pref" value="<?= e($r['task_pref']) ?>"></div>
            <div class="field"><label>Skills</label><input name="skills" value="<?= e($r['skills']) ?>"></div>
            <div class="field"><label>Projects</label><textarea name="projects" rows="3"><?= e($r['projects']) ?></textarea></div>
            <div class="field"><label>Task history</label><textarea name="task_history" rows="3"><?= e($r['task_history']) ?></textarea></div>
            <div class="field"><label>Notes</label><textarea name="notes" rows="3"><?= e($r['notes']) ?></textarea></div>
          </div>
          <div class="actions"><button class="btn btn-primary">Save record</button><a class="btn btn-ghost" href="<?= e(url('/admin/admin-volunteers')) ?>">Cancel</a></div>
        </form>
      </td></tr>
      <?php endif; ?>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
