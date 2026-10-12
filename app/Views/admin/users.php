<?php $title = 'Admin Dashboard'; $active = 'admin'; $super = \App\Core\Auth::isSuperadmin($u); ?>
<div class="card">
  <div class="card-head">
    <div class="tabs" style="margin:0;border:0"><?php foreach ($lists as $k => $l): ?><a class="tab <?= $type === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/users?type=' . $k)) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
    <span class="grow"></span>
    <a class="btn btn-sm" href="<?= e(url('/admin/users/export.csv?type=' . $type)) ?>"><?= icon('download', '', 16) ?> Full CSV</a>
    <?php if ($type !== 'pending' && ($super || $type === 'newmuslim')): ?><a class="btn btn-primary" href="<?= e(url('/admin/users/new?type=' . $type)) ?>"><?= icon('plus', '', 16) ?> Add <?= e(rtrim($lists[$type], 's')) ?></a><?php endif; ?>
  </div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="15" data-empty="No records.">
    <thead><tr>
      <th>Created</th>
      <?php if ($type === 'pending'): ?><th>Email</th><th data-filter>Type</th><th>Last sign-in</th>
      <?php else: ?><th>First name</th><th>Last name</th><th>Email</th><th data-filter>Status</th><th data-filter>Gender</th><th>Phone</th><th data-filter>City</th>
        <?php if ($type === 'volunteer'): ?><th>PIN</th><th data-filter>ID check</th><th data-filter>Roles</th><th data-filter>Teams</th><th>Leads</th><th>Mentees</th><th>Last sign-in</th><?php endif; ?>
        <?php if ($type === 'newmuslim'): ?><th data-filter>Referred by</th><th>Mentors</th><th data-filter>Previous religion</th><?php endif; ?>
      <?php endif; ?>
    </tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr <?= $type !== 'pending' ? 'data-href="' . e(url('/admin/users/' . $r['id'])) . '" class="row-link"' : '' ?>>
        <td data-value="<?= e($r['created_at']) ?>"><?= e(fmt_date($r['created_at'])) ?></td>
        <?php if ($type === 'pending'): ?><td><?= e($r['email']) ?></td><td><?= e(ucfirst((string) $r['type'])) ?></td><td><?= e(fmt_datetime($r['last_login_at'])) ?></td>
        <?php else: ?>
          <td><?= e($r['first_name']) ?></td><td><?= e($r['last_name']) ?></td><td><?= e($r['email']) ?></td><td><span class="badge badge-<?= e($r['status']) ?>"><?= e(\App\Models\User::statusLabel($r)) ?></span></td><td><?= e(ucfirst((string) $r['gender'])) ?></td><td><?= e(\App\Models\User::phoneOf($r)) ?></td><td><?= e($r['city']) ?></td>
          <?php if ($type === 'volunteer'): ?><td><?= e($r['pin']) ?></td><td><?= e($r['stripe_status'] ?: '-') ?></td><td><?= e(implode(', ', array_map('start_case', $r['sub_groups']))) ?></td><td><?= e(implode(', ', array_map(fn($t) => $teamNames[$t] ?? $t, $r['teams']))) ?></td><td><?= e(implode(', ', array_map(fn($t) => $teamNames[$t] ?? $t, $r['admin_teams']))) ?></td><td><?= (int) ($menteeCounts[$r['id']] ?? 0) ?></td><td data-value="<?= e($r['last_login_at']) ?>"><?= e(fmt_date($r['last_login_at'])) ?></td><?php endif; ?>
          <?php if ($type === 'newmuslim'): ?><td><?= $r['created_by'] ? e($referrers[$r['created_by']] ?? 'Unknown') : 'Self' ?></td><td><?php foreach ($assigned[$r['id']] ?? [] as $a): ?><span class="chip"><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?><?= $a['accepted_at'] ? ' (accepted)' : ' (pending)' ?></span><?php endforeach; ?></td><td><?= e($r['previous_religion']) ?></td><?php endif; ?>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>
