<?php $title = 'Mentees'; $active = 'mentees'; $super = \App\Core\Auth::isSuperadmin($u); ?>
<div class="card">
  <p class="muted small">Green: all assigned mentors accepted · Yellow: waiting for a mentor to accept · Red: no mentor assigned yet.</p>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="15" data-export="mentees.csv" data-empty="No mentees assigned to you yet.">
    <thead><tr><th>Accepted</th><th>Registered</th><th>First name</th><th>Last name</th><th>Phone</th><th>Email</th><th data-filter>Status</th><th data-filter>City</th><th data-filter>Gender</th><th>Mentors</th><th data-nosort></th></tr></thead>
    <tbody><?php foreach ($mentees as $m): $cls = !$m['assigned'] ? 'row-red' : ($m['all_accepted'] ? 'row-green' : 'row-yellow'); $mine = $m['my_assignment']; ?>
      <tr class="<?= $cls ?>">
        <td data-value="<?= $m['all_accepted'] ? 1 : 0 ?>"><?= $m['all_accepted'] ? '<span class="yes">Yes</span>' : '<span class="muted">No</span>' ?></td>
        <td data-value="<?= e($m['created_at']) ?>"><?= e(fmt_date($m['created_at'])) ?></td>
        <td><?= e($m['first_name']) ?></td><td><?= e($m['last_name']) ?></td><td><?= e(\App\Models\User::phoneOf($m)) ?></td><td><?= e($m['email']) ?></td>
        <td><span class="badge badge-<?= e($m['status']) ?>"><?= e(\App\Models\User::statusLabel($m)) ?></span></td><td><?= e($m['city']) ?></td><td><?= e(ucfirst((string) $m['gender'])) ?></td>
        <td><?php foreach ($m['assigned'] as $a): ?><span class="chip" title="<?= $a['accepted_at'] ? 'Accepted' : ($a['rejected_at'] ? 'Declined' : 'Pending') ?>"><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?><?= $a['accepted_at'] ? ' ✓' : ($a['rejected_at'] ? ' ✕' : ' …') ?></span><?php endforeach; ?></td>
        <td class="actions">
          <?php if ($super): ?><a class="btn btn-sm" href="<?= e(url('/mentees/' . $m['id'] . '/assign?back=/mentees')) ?>">Assign</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/users/' . $m['id'] . '?back=/mentees')) ?>">Edit</a>
          <?php elseif ($mine && !$mine['accepted_at'] && !$mine['rejected_at']): ?>
            <button type="button" class="btn btn-sm btn-primary" data-open="accept-<?= e($m['id']) ?>">Accept / decline</button>
            <dialog class="modal" id="accept-<?= e($m['id']) ?>"><div class="modal-body">
              <h2>Would you like to mentor <?= e($m['first_name']) ?>?</h2>
              <form method="post" action="<?= e(url('/mentees/' . $m['id'] . '/accept')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary">Yes, accept</button></form>
              <hr style="margin:16px 0;border:0;border-top:1px solid var(--line)">
              <form method="post" action="<?= e(url('/mentees/' . $m['id'] . '/reject')) ?>"><?= csrf_field() ?><div class="field"><label>If not, why? (the coordinator will reassign)</label><textarea name="reason" rows="2"></textarea></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="accept-<?= e($m['id']) ?>">Cancel</button><button class="btn btn-danger">Decline</button></div></form>
            </div></dialog>
          <?php else: ?><a class="btn btn-sm" href="<?= e(url('/mentees/' . $m['id'])) ?>">Open</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
</div>
