<?php $title = 'My Teams'; $active = 'teams'; ?>
<div class="card">
  <?php if (!$teams): ?><p class="empty">You are not a member of any team yet.</p><?php else: ?>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25">
    <thead><tr><th>Team</th><th>Group</th><th>Members</th><th>Leads</th><th>Upcoming events</th><th></th></tr></thead>
    <tbody><?php foreach ($teams as $t): ?>
      <tr data-href="<?= e(url('/teams/my/' . $t['id'])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong></td><td><?= e(start_case($t['parent_team'] ?? '')) ?></td><td><?= (int) $t['members_count'] ?></td><td><?= (int) $t['admins_count'] ?></td><td><?= (int) $t['active_events'] ?></td><td><?= in_array($t['id'], $u['admin_teams'], true) ? '<span class="badge badge-approved">Lead</span>' : '' ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
