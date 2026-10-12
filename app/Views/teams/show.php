<?php
$title = $team['name']; $active = 'teams';
$breadcrumbs = ['My Teams' => '/teams/my', $team['name'] => null];
$isSuper = \App\Core\Auth::isSuperadmin($u);
?>
<div class="card">
  <div class="card-head">
    <h2><?= $view === 'pending' ? 'Pending volunteers' : 'Team members' ?></h2>
    <span class="grow"></span>
    <?php if (count($allTeams) > 1): ?>
    <form method="get" action="<?= e(url('/teams/my')) ?>" class="inline"><select class="input" onchange="location.href='<?= e(url('/teams/my/')) ?>'+this.value"><?php foreach ($allTeams as $t): ?><option value="<?= e($t['id']) ?>" <?= $t['id'] === $team['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></form>
    <?php endif; ?>
    <?php if ($isLead): ?>
    <div class="switch"><a class="<?= $view === 'members' ? 'is-active' : '' ?>" href="<?= e(url('/teams/my/' . $team['id'])) ?>">My team</a><a class="<?= $view === 'pending' ? 'is-active' : '' ?>" href="<?= e(url('/teams/my/' . $team['id'] . '?view=pending')) ?>">Pending volunteers</a></div>
    <?php endif; ?>
  </div>
  <?php if ($view === 'pending'): ?>
    <p class="muted">Volunteers who completed registration but are not in a team yet. Add them to <?= e($team['name']) ?>.</p>
    <div class="table-wrap"><table class="data-table" data-table data-empty="No pending volunteers.">
      <thead><tr><th>Name</th><th>Email</th><th>City</th><th>Position</th><th data-filter>Status</th><th>Registered</th><th data-nosort></th></tr></thead>
      <tbody><?php foreach ($pending as $p): ?>
        <tr><td><a href="<?= e(url('/admin/users/' . $p['id'] . '?back=' . urlencode('/teams/my/' . $team['id'] . '?view=pending'))) ?>"><?= e(\App\Models\User::fullName($p)) ?></a></td><td><?= e($p['email']) ?></td><td><?= e($p['city']) ?></td><td><?= e(start_case($p['position'] ?? '')) ?></td><td><span class="badge badge-<?= e($p['status']) ?>"><?= e(\App\Models\User::statusLabel($p)) ?></span></td><td data-value="<?= e($p['created_at']) ?>"><?= e(fmt_date($p['created_at'])) ?></td>
        <td><form method="post" action="<?= e(url('/teams/' . $team['id'] . '/add-member')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($p['id']) ?>"><input type="hidden" name="from" value="pending"><button class="btn btn-sm btn-primary">Add to my team</button></form></td></tr>
      <?php endforeach; ?></tbody></table></div>
  <?php else: ?>
    <div class="table-wrap"><table class="data-table" data-table data-export="<?= e($team['id']) ?>_members.csv" data-empty="No members yet.">
      <thead><tr><th>Lead</th><th>First name</th><th>Last name</th><?php if ($isLead): ?><th>Registered</th><th data-filter>Status</th><th data-filter>ID check</th><?php endif; ?><th>Email</th><th data-filter>Gender</th><th>Phone</th><?php if ($isLead): ?><th data-nosort></th><?php endif; ?></tr></thead>
      <tbody><?php foreach ($members as $m): $lead = in_array($m['id'], $adminIds, true); ?>
        <tr <?= $isLead ? 'data-href="' . e(url('/admin/users/' . $m['id'] . '?back=' . urlencode('/teams/my/' . $team['id']))) . '" class="row-link"' : '' ?>>
          <td><?= $lead ? '<span class="yes">Yes</span>' : '' ?></td><td><?= e($m['first_name']) ?></td><td><?= e($m['last_name']) ?></td>
          <?php if ($isLead): ?><td data-value="<?= e($m['created_at']) ?>"><?= e(fmt_date($m['created_at'])) ?></td><td><span class="badge badge-<?= e($m['status']) ?>"><?= e(\App\Models\User::statusLabel($m)) ?></span></td><td><?= e($m['stripe_status'] ?: '-') ?></td><?php endif; ?>
          <td><?= e($m['email']) ?></td><td><?= e(ucfirst((string) $m['gender'])) ?></td><td><?= e(\App\Models\User::phoneOf($m)) ?></td>
          <?php if ($isLead): ?><td><?php if ($m['id'] !== $u['id']): ?><form method="post" action="<?= e(url('/teams/' . $team['id'] . '/remove-member')) ?>" data-confirm="Remove <?= e(\App\Models\User::fullName($m)) ?> from <?= e($team['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($m['id']) ?>"><button class="btn btn-sm btn-ghost">Remove</button></form><?php endif; ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
