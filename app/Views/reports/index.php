<?php
$label = ($status === 'pending' ? 'Pending ' : 'Submitted ') . ($kind === 'mentor' ? 'Mentor Reports' : 'Event Reports');
$title = $label; $active = 'reports-' . $kind . '-' . $status;
$cols = [];
foreach ($fields as $f) { if (!empty($f['name']) && !in_array($f['type'] ?? '', ['section', 'text_copy', 'typography', 'upload', 'textarea'], true)) { $cols[] = $f; } }
?>
<div class="card">
  <div class="card-head">
    <div class="tabs" style="margin:0;border:0">
      <a class="tab <?= $status === 'pending' ? 'is-active' : '' ?>" href="<?= e(url('/reports/' . $kind . '/pending')) ?>">Pending</a>
      <a class="tab <?= $status === 'submitted' ? 'is-active' : '' ?>" href="<?= e(url('/reports/' . $kind . '/submitted')) ?>">Submitted</a>
    </div>
    <span class="grow"></span>
    <?php if ($canTeam): ?><div class="switch"><a class="<?= $scope === 'team' ? 'is-active' : '' ?>" href="?scope=team"><?= \App\Core\Auth::isSuperadmin($u) ? 'All reports' : 'Team reports' ?></a><a class="<?= $scope === 'mine' ? 'is-active' : '' ?>" href="?scope=mine">My reports</a></div><?php endif; ?>
    <a class="btn btn-sm" href="<?= e(url('/reports/' . $kind . '/export.csv?status=' . $status)) ?>">⬇ Full CSV</a>
  </div>
  <?php if ($status === 'pending'): ?><p class="muted small">Rows in red are more than 2 days old. Click a row to submit the report<?= $canTeam ? ' (leads can submit on behalf of a volunteer)' : '' ?>.</p><?php endif; ?>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="15" data-empty="No <?= e(strtolower($label)) ?>.">
    <thead><tr>
      <th><?= $kind === 'mentor' ? 'Mentee' : 'Event' ?></th><th><?= $kind === 'mentor' ? 'Week' : 'Event date' ?></th>
      <?php if ($status === 'submitted'): ?><th>Submitted</th><?php else: ?><th>Opened</th><?php endif; ?>
      <th data-filter>Submitter</th><th data-filter>Team</th>
      <?php if ($kind === 'mentor'): ?><th>Phone</th><th data-filter>City</th><?php endif; ?>
      <?php if ($status === 'submitted'): foreach ($cols as $c): ?><th><?= e($c['label']) ?></th><?php endforeach; endif; ?>
    </tr></thead>
    <tbody><?php foreach ($rows as $r): $old = $status === 'pending' && strtotime($r['created_at']) < time() - 2 * 86400; ?>
      <tr data-href="<?= e(url('/reports/' . $r['id'])) ?>" class="row-link <?= $old ? 'row-red' : '' ?>">
        <td><strong><?= e($kind === 'mentor' ? $r['mentee_name'] : $r['event_name']) ?></strong></td>
        <td data-value="<?= e($kind === 'mentor' ? $r['period_key'] : $r['start_at']) ?>"><?= e($kind === 'mentor' ? $r['period_key'] : fmt_datetime($r['start_at'])) ?></td>
        <td data-value="<?= e($status === 'submitted' ? $r['submitted_at'] : $r['created_at']) ?>"><?= e(fmt_datetime($status === 'submitted' ? $r['submitted_at'] : $r['created_at'])) ?><?= $r['submitted_by'] ? '<br><small class="muted">by a lead</small>' : '' ?></td>
        <td><?= e($r['assignee_name']) ?></td><td><?= e(implode(', ', $r['team_names'])) ?></td>
        <?php if ($kind === 'mentor'): ?><td><?= e($r['mobile']) ?></td><td><?= e($r['city']) ?></td><?php endif; ?>
        <?php if ($status === 'submitted'): foreach ($cols as $c): ?><td><?= e(\App\Services\FormRenderer::displayValue($c, $r['submit'][$c['name']] ?? null)) ?></td><?php endforeach; endif; ?>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>
