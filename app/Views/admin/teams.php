<?php $title = 'Teams'; $active = 'admin-teams'; ?>
<div class="card">
  <div class="card-head"><h2>All teams</h2><span class="grow"></span><a class="btn btn-primary" href="<?= e(url('/admin/teams/new')) ?>"><?= icon('plus', '', 16) ?> New team</a></div>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-export="teams.csv">
    <thead><tr><th>Team</th><th data-filter>Group</th><th>Members</th><th>Leads</th><th>Upcoming events</th><th data-filter>Status</th><th data-nosort></th></tr></thead>
    <tbody><?php foreach ($teams as $t): ?><tr data-href="<?= e(url('/admin/teams/' . $t['id'])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong><br><small class="muted"><?= e($t['id']) ?></small></td><td><?= e(start_case($t['parent_team'] ?? '')) ?></td><td><?= (int) $t['members_count'] ?></td><td><?= (int) $t['admins_count'] ?></td><td><?= (int) $t['active_events'] ?></td><td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td><td><a class="btn btn-sm" href="<?= e(url('/admin/teams/' . $t['id'])) ?>">Manage</a></td></tr><?php endforeach; ?></tbody></table></div>
</div>
