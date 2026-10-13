<?php
use App\Models\Application;
use App\Services\AdminReview;
$titles = ['applicants' => 'All applications', 'onboarded' => 'Onboarded volunteers', 'old_rejected' => 'Old / rejected volunteers'];
$title = $titles[$mode];
$active = ['applicants' => 'cc-apps-all', 'onboarded' => 'cc-apps-onboarded', 'old_rejected' => 'cc-apps-old'][$mode];
$super = \App\Core\Auth::isSuperadmin($u);
$base = '/admin/applications?mode=' . $mode;
$qs = fn(array $extra) => $base . '&' . http_build_query(array_filter(['status' => $status, 'category' => $category, 'q' => $q] + $extra, fn($v) => $v !== '' && $v !== null));
$statusOrder = ['New', 'Under Review', 'Approved', 'Onboarded', 'Waitlisted', 'Rejected', 'Inactive'];
$badge = fn(string $d) => ['New' => 'new', 'Under Review' => 'in_review', 'Approved' => 'approved', 'Onboarded' => 'completed', 'Waitlisted' => 'paused', 'Rejected' => 'rejected', 'Inactive' => 'inactive'][$d] ?? 'default';
?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach ($titles as $k => $l): ?><a class="tab <?= $mode === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/applications?mode=' . $k)) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
  <span class="grow"></span>
  <a class="btn btn-sm" href="<?= e(url('/admin/applications/export.csv')) ?>"><?= icon('download', '', 16) ?> Export CSV</a>
</div>

<?php if ($requests): ?>
<div class="card" style="border-color:var(--primary)">
  <div class="card-head"><h2><?= icon('shield', '', 18) ?> Admin access requests</h2><span class="grow"></span><span class="small muted"><?= count($requests) ?> pending</span></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Requested</th><th>Name</th><th>Email</th><th>Role</th><th>Reason</th><th></th></tr></thead><tbody>
  <?php foreach ($requests as $r): ?><tr>
    <td><?= e(fmt_date($r['created_at'])) ?></td><td><strong><?= e($r['full_name']) ?></strong></td><td><?= e($r['email']) ?></td><td><span class="chip"><?= e(start_case($r['requested_role'])) ?></span></td><td class="small"><?= e($r['reason'] ?: '—') ?></td>
    <td><?php if ($super): ?><div class="actions">
      <form method="post" action="<?= e(url('/admin/signup-requests/' . $r['id'])) ?>" data-confirm="Grant <?= e($r['full_name']) ?> the <?= e($r['requested_role']) ?> role?"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
      <form method="post" action="<?= e(url('/admin/signup-requests/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="reason" value="Not approved by an administrator."><button class="btn btn-sm btn-ghost">Reject</button></form>
    </div><?php else: ?><span class="small muted">Superadmin decides</span><?php endif; ?></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($mode === 'applicants' && ($notReady || $flaggedCount)): ?>
<div class="actions" style="margin-bottom:12px">
  <?php if ($notReady): ?><a class="pill <?= $attention ? 'pill-accent' : '' ?>" href="<?= e(url($attention ? $qs([]) : $qs(['attention' => 1]))) ?>"><?= icon('clock', '', 14) ?> <?= $notReady ?> still completing onboarding</a><?php endif; ?>
  <?php if ($flaggedCount): ?><a class="pill <?= $flagged ? 'pill-accent' : '' ?>" href="<?= e(url($flagged ? $qs([]) : $qs(['flagged' => 1]))) ?>"><?= icon('flag', '', 14) ?> <?= $flaggedCount ?> flagged for data quality</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="actions" style="margin-bottom:12px">
    <a class="pill <?= $status === '' ? 'pill-accent' : '' ?>" href="<?= e(url($qs(['status' => '']))) ?>">All <strong><?= $total ?></strong></a>
    <?php foreach ($statusOrder as $st): if (empty($counts[$st])) { continue; } ?><a class="pill <?= $status === $st ? 'pill-accent' : '' ?>" href="<?= e(url($qs(['status' => $st]))) ?>"><?= e($st) ?> <strong><?= $counts[$st] ?></strong></a><?php endforeach; ?>
  </div>
  <form class="table-toolbar" method="get" action="<?= e(url('/admin/applications')) ?>">
    <input type="hidden" name="mode" value="<?= e($mode) ?>"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name, email, country, city, phone…">
    <select name="category"><option value="">All categories</option><?php foreach (Application::CATEGORIES as $k => $v): ?><option value="<?= e($k) ?>" <?= $category === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-dark">Filter</button>
    <?php if ($q || $category || $status || $focus || $attention || $flagged): ?><a class="btn btn-sm btn-ghost" href="<?= e(url($base)) ?>">Clear</a><?php endif; ?>
  </form>
  <?php if ($focus): ?><div class="flash flash-info">Showing one applicant: <strong><?= e($focus['name']) ?></strong>. <a href="<?= e(url($base)) ?>">Show everyone</a></div><?php endif; ?>
  <?php if (!$rows): ?><p class="empty">No applications match.</p><?php else: ?>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-empty="No records.">
    <thead><tr><th>Applied</th><th>Name</th><th data-filter>Category</th><th data-filter>Country</th><th>Roles</th><th>Readiness</th><th data-filter>Status</th><?php if ($mode === 'onboarded'): ?><th>Onboarded</th><?php else: ?><th data-filter>Interview</th><?php endif; ?><th></th></tr></thead>
    <tbody><?php foreach ($rows as $a): $r = AdminReview::readiness($a); $d = Application::displayStatus($a); $issues = AdminReview::dataQualityIssues($a); ?>
      <tr data-href="<?= e(url('/admin/applications/' . $a['id'])) ?>" class="row-link">
        <td data-value="<?= e($a['created_at']) ?>"><?= e(fmt_date($a['created_at'])) ?><br><span class="small muted">#<?= e(strtoupper(substr($a['id'], -6))) ?></span></td>
        <td><strong><?= e($a['name'] ?: '(no name)') ?></strong><?php if ($issues): ?> <span title="<?= e(implode('; ', $issues)) ?>" style="color:var(--primary)"><?= icon('flag', '', 14) ?></span><?php endif; ?><br><span class="small muted"><?= e($a['email']) ?></span></td>
        <td><?= e($a['volunteer_category']) ?></td>
        <td><?= e($a['country'] ?: '—') ?><?= $a['city'] ? '<br><span class="small muted">' . e($a['city']) . '</span>' : '' ?></td>
        <td><?php if (Application::isCanada($a)): foreach (Application::canadaAbbrs($a) as $role => $ab): ?><span class="chip" title="<?= e($role) ?>" style="<?= in_array($role, $a['onboarding_approved_roles'], true) ? 'background:var(--accent-50);border-color:var(--accent)' : '' ?>"><?= e($ab) ?></span><?php endforeach; else: ?><span class="small"><?= e(implode(', ', array_slice($a['role_interests'], 0, 3)) ?: '—') ?></span><?php endif; ?></td>
        <td class="small" style="white-space:nowrap">
          <span title="Agreements" style="color:<?= $r['agreementsDone'] ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($r['agreementsDone'] ? 'check-circle' : 'circle', '', 15) ?></span>
          <span title="ID documents" style="color:<?= $r['idsDone'] ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($r['idsDone'] ? 'check-circle' : 'circle', '', 15) ?></span>
          <span title="CV" style="color:<?= $r['cvDone'] ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($r['cvDone'] ? 'check-circle' : 'circle', '', 15) ?></span>
          <span title="Onboarding stages" style="color:<?= $r['onboardingDone'] ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($r['onboardingDone'] ? 'check-circle' : 'circle', '', 15) ?></span>
          <?= $r['ready'] ? '<span class="muted">ready</span>' : '<span class="muted">' . Application::completionPercent($a) . '%</span>' ?>
        </td>
        <td><span class="badge badge-<?= e($badge($d)) ?>"><?= e($d) ?></span></td>
        <?php if ($mode === 'onboarded'): ?><td data-value="<?= e($a['onboarded_date'] ?? '') ?>"><?= e(fmt_date($a['onboarded_date'] ?: $a['onboarded_approved_at'])) ?></td>
        <?php else: ?><td class="small"><?= e(['not_scheduled' => 'Not scheduled', 'scheduled' => 'Scheduled', 'completed' => 'Completed · ' . $a['interview_outcome']][$a['interview_status']] ?? $a['interview_status']) ?></td><?php endif; ?>
        <td><a class="btn btn-sm" href="<?= e(url('/admin/applications/' . $a['id'])) ?>">Review</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
