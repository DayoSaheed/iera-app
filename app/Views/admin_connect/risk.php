<?php
use App\Models\Application;
$title = 'Risk assessments (UK)'; $active = 'cc-risk';
?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach ($tabs as $k => $list): ?><a class="tab <?= $tab === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/risk-assessments?tab=' . urlencode($k))) ?>"><?= e($k) ?> <span class="nav-count" style="margin-left:6px"><?= count($list) ?></span></a><?php endforeach; ?></div>
</div>
<div class="<?= $open ? 'two-col' : '' ?>">
  <div class="card">
    <p class="small muted">UK volunteers who applied for Fundraising or Call Centre roles need a risk assessment before approval. Applicants arrive here once their onboarding is complete, and are notified by email.</p>
    <form class="table-toolbar" method="get" action="<?= e(url('/admin/risk-assessments')) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name, email, city…"><button class="btn btn-sm btn-dark">Search</button></form>
    <?php if (!$rows): ?><p class="empty">No assessments at this stage.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Applicant</th><th>Roles</th><th>Status</th><th>Assessor</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $a): ?>
        <tr <?= $open && $open['id'] === $a['id'] ? 'style="background:var(--accent-50)"' : '' ?>>
          <td><strong><?= e($a['name']) ?></strong><br><span class="small muted"><?= e($a['email']) ?><?= $a['city'] ? ' · ' . e($a['city']) : '' ?></span></td>
          <td><?php foreach (Application::ukAbbrs($a) as $role => $ab): ?><span class="chip" title="<?= e($role) ?>"><?= e($ab) ?></span><?php endforeach; ?></td>
          <td class="small"><?= e($a['risk_assessment_status']) ?><br><span class="muted"><?= e($a['risk_assessment_outcome']) ?><?= $a['risk_assessment_date'] ? ' · ' . e(fmt_date($a['risk_assessment_date'])) : '' ?></span></td>
          <td class="small"><?= e($a['risk_assessor_name'] ?: '—') ?></td>
          <td><a class="btn btn-sm" href="<?= e(url('/admin/risk-assessments?tab=' . urlencode($tab) . '&open=' . $a['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <?php if ($open): $a = $open; ?>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2><?= e($a['name']) ?></h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/applications/' . $a['id'])) ?>">Full application</a></div>
      <dl class="kv"><dt>Email</dt><dd><?= e($a['email']) ?></dd><dt>Phone</dt><dd><?= e($a['phone'] ?: '—') ?></dd><dt>Roles</dt><dd><?= e(implode(', ', Application::ukRoles($a)) ?: '—') ?></dd><dt>Interview</dt><dd><?= e(start_case($a['interview_status'])) ?><?= $a['interview_status'] === 'completed' ? ' · ' . e($a['interview_outcome']) : '' ?></dd><dt>Notified</dt><dd><?= e(fmt_datetime($a['risk_assessment_notified_at']) ?: 'not yet') ?></dd></dl>
    </div>
    <div class="card">
      <h2>Assessment</h2>
      <form method="post" action="<?= e(url('/admin/applications/' . $a['id'] . '/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="ra"><input type="hidden" name="back" value="/admin/risk-assessments?tab=<?= e(urlencode($tab)) ?>&open=<?= e($a['id']) ?>">
        <div class="grid-2">
          <div class="field"><label>Stage</label><select name="risk_assessment_stage"><?php foreach (array_keys($tabs) as $s): ?><option <?= $a['risk_assessment_stage'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Outcome</label><select name="risk_assessment_outcome"><?php foreach (['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed', 'query' => 'Query raised'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $a['risk_assessment_outcome'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Date</label><input type="date" name="risk_assessment_date" value="<?= e($a['risk_assessment_date'] ?: date('Y-m-d')) ?>"></div>
          <div class="field"><label>Assessor</label><input name="risk_assessor_name" value="<?= e($a['risk_assessor_name'] ?: \App\Models\User::fullName(\App\Core\Auth::user())) ?>"></div>
        </div>
        <div class="field"><label>Notes</label><textarea name="risk_assessment_notes" rows="5"><?= e($a['risk_assessment_notes']) ?></textarea></div>
        <button class="btn btn-primary">Save assessment</button>
        <span class="field-hint">Passed or failed marks the assessment completed; the applicant can then be approved.</span>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
