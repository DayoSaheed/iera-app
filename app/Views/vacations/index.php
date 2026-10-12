<?php $title = 'Vacation Requests'; $active = 'vacations'; ?>
<div class="card">
  <div class="card-head"><h2><?= $scope === 'team' ? 'Requests from your team' : 'My requests' ?></h2><span class="grow"></span>
    <?php if ($manage): ?><div class="switch"><a class="<?= $scope === 'team' ? 'is-active' : '' ?>" href="?scope=team">Team</a><a class="<?= $scope === 'mine' ? 'is-active' : '' ?>" href="?scope=mine">Mine</a></div><?php endif; ?>
    <button type="button" class="btn btn-primary" data-open="newVacation"><?= icon('plus', '', 16) ?> New request</button></div>
  <div class="table-wrap"><table class="data-table" data-table data-empty="No vacation requests.">
    <thead><tr><?php if ($scope === 'team'): ?><th>Volunteer</th><th>Email</th><?php endif; ?><th>Requested</th><th>Reason</th><th>From</th><th>To</th><th data-filter>Status</th><th data-nosort></th></tr></thead>
    <tbody><?php foreach ($rows as $v): $cls = $v['status'] === 'approved' ? 'row-green' : ($v['status'] === 'denied' ? 'row-red' : 'row-yellow'); ?>
      <tr class="<?= $cls ?>"><?php if ($scope === 'team'): ?><td><?= e(trim($v['first_name'] . ' ' . $v['last_name'])) ?></td><td><?= e($v['email']) ?></td><?php endif; ?>
        <td data-value="<?= e($v['created_at']) ?>"><?= e(fmt_date($v['created_at'])) ?></td><td><?= e($v['reason']) ?></td><td><?= e(fmt_date($v['from_date'])) ?></td><td><?= e(fmt_date($v['to_date'])) ?></td><td><span class="badge badge-<?= e($v['status']) ?>"><?= e($v['status']) ?></span></td>
        <td class="actions">
          <?php if ($scope === 'team' && $v['user_id'] !== $u['id']): ?>
            <?php foreach (['approved' => 'Approve', 'denied' => 'Deny', 'requested' => 'Reset'] as $st => $lbl): if ($st === $v['status']) continue; ?><form method="post" action="<?= e(url('/vacations/' . $v['id'] . '/status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>"><button class="btn btn-sm <?= $st === 'approved' ? 'btn-primary' : 'btn-ghost' ?>"><?= $lbl ?></button></form><?php endforeach; ?>
          <?php elseif ($v['user_id'] === $u['id'] && $v['status'] === 'requested'): ?>
            <button type="button" class="btn btn-sm" data-modal="editVacation" data-fill-action="<?= e(url('/vacations/' . $v['id'])) ?>" data-fill-reason="<?= e($v['reason']) ?>" data-fill-from-date="<?= e($v['from_date']) ?>" data-fill-to-date="<?= e($v['to_date']) ?>">Edit</button>
          <?php endif; ?>
        </td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<dialog class="modal" id="newVacation"><form method="post" action="<?= e(url('/vacations')) ?>" class="modal-body"><?= csrf_field() ?><h2>New vacation request</h2>
  <div class="field"><label>Reason</label><textarea name="reason" rows="2"></textarea></div>
  <div class="grid-2"><div class="field is-required"><label>From <span class="req">*</span></label><input type="date" name="from_date" required></div><div class="field is-required"><label>To <span class="req">*</span></label><input type="date" name="to_date" required></div></div>
  <div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="newVacation">Cancel</button><button class="btn btn-primary">Submit</button></div></form></dialog>
<dialog class="modal" id="editVacation"><form method="post" action="" class="modal-body"><?= csrf_field() ?><h2>Edit request</h2>
  <div class="field"><label>Reason</label><textarea name="reason" rows="2"></textarea></div>
  <div class="grid-2"><div class="field"><label>From</label><input type="date" name="from_date" required></div><div class="field"><label>To</label><input type="date" name="to_date" required></div></div>
  <div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="editVacation">Cancel</button><button class="btn btn-primary">Save</button></div></form></dialog>
