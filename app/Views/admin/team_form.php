<?php $title = $team ? $team['name'] : 'New team'; $active = 'admin-teams'; $breadcrumbs = ['Teams' => '/admin/teams', $title => null]; ?>
<div class="grid-2">
  <div class="card"><h2><?= $team ? 'Team details' : 'Create team' ?></h2>
    <form method="post" action="<?= e(url($team ? '/admin/teams/' . $team['id'] : '/admin/teams/new')) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="save">
      <?= $formHtml ?>
      <?php if ($team): ?><div class="field"><label>Status</label><select name="status"><option value="approved" <?= $team['status'] === 'approved' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $team['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div><?php endif; ?>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/teams')) ?>">Back</a><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
  <?php if ($team): ?>
  <div class="card"><h2>Members (<?= count($members) ?>)</h2>
    <form method="post" action="<?= e(url('/admin/teams/' . $team['id'])) ?>" class="actions" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="add_member">
      <select name="user_id" class="input" style="max-width:320px"><option value="">Add a volunteer…</option><?php foreach ($volunteers as $v): if (in_array($v['id'], array_column($members, 'id'), true)) continue; ?><option value="<?= e($v['id']) ?>"><?= e(\App\Models\User::fullName($v)) ?> (<?= e($v['email']) ?>)</option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Add</button></form>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Status</th><th>Lead</th><th></th></tr></thead><tbody>
    <?php foreach ($members as $m): $lead = in_array($m['id'], $adminIds, true); ?><tr><td><a href="<?= e(url('/admin/users/' . $m['id'] . '?back=' . urlencode('/admin/teams/' . $team['id']))) ?>"><?= e(\App\Models\User::fullName($m)) ?></a></td><td><span class="badge badge-<?= e($m['status']) ?>"><?= e(\App\Models\User::statusLabel($m)) ?></span></td><td><?= $lead ? '<span class="yes">Yes</span>' : '' ?></td>
      <td class="actions"><form method="post" action="<?= e(url('/admin/teams/' . $team['id'])) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_lead"><input type="hidden" name="user_id" value="<?= e($m['id']) ?>"><button class="btn btn-sm btn-ghost"><?= $lead ? 'Remove lead' : 'Make lead' ?></button></form><form method="post" action="<?= e(url('/admin/teams/' . $team['id'])) ?>" class="inline" data-confirm="Remove from team?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="user_id" value="<?= e($m['id']) ?>"><button class="btn btn-sm btn-ghost">Remove</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>
</div>
