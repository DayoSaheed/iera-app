<?php
use App\Services\AdminReview;
$title = 'Hierarchy'; $active = 'cc-hierarchy';
$tops = array_values(array_filter($regions, fn($r) => empty($r['parent_id'])));
$children = [];
foreach ($regions as $r) { if ($r['parent_id']) { $children[$r['parent_id']][] = $r; } }
$byRole = [];
foreach ($assignments as $m) { $byRole[$m['management_role']][] = $m; }
$byRegion = [];
foreach ($assignments as $m) { $byRegion[$m['region_name'] ?: ''][] = $m; }
$ra = $editAssignment ?? [];
$rr = $editRegion ?? [];
?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach (['chart' => 'Org chart', 'regions' => 'Regions', 'assignments' => 'Managers & coordinators'] as $k => $l): ?><a class="tab <?= $tab === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/hierarchy?tab=' . $k)) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
</div>

<?php if ($tab === 'chart'): ?>
<?php if (!$regions): ?>
<div class="card"><p class="empty">No regions yet. <a href="<?= e(url('/admin/hierarchy?tab=regions')) ?>">Create the default regions</a> to get started.</p></div>
<?php else: ?>
<div class="card">
  <div class="card-head"><h2>Leadership</h2><span class="grow"></span><span class="small muted"><?= count($assignments) ?> active assignment(s)</span></div>
  <div class="grid-3">
    <?php foreach (['Super Admin', 'Global Volunteer Director', 'UK Volunteer Manager', 'Online Volunteer Manager'] as $role): ?>
    <div class="stat plain"><div class="stat-label"><?= e($role) ?></div><?php if (empty($byRole[$role])): ?><div class="muted small" style="margin-top:6px">Unassigned</div><?php else: foreach ($byRole[$role] as $m): ?><div style="margin-top:6px"><strong><?= e($m['user_name'] ?: $m['user_email']) ?></strong><br><span class="small muted"><?= e($m['user_email']) ?></span></div><?php endforeach; endif; ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php foreach (['UK' => 'UK branch', 'Global' => 'Global branch'] as $branch => $label): $bt = array_filter($tops, fn($r) => $r['branch'] === $branch); if (!$bt) { continue; } ?>
<div class="card">
  <div class="card-head"><h2><?= e($label) ?></h2></div>
  <div class="grid-3">
    <?php foreach ($bt as $top): ?>
    <div style="border:1px solid var(--line);border-radius:12px;padding:14px">
      <strong><?= e(($top['emoji'] ? $top['emoji'] . ' ' : '') . $top['name']) ?></strong>
      <?php foreach ($byRegion[$top['name']] ?? [] as $m): ?><div class="small"><?= e($m['management_role']) ?>: <strong><?= e($m['user_name'] ?: $m['user_email']) ?></strong></div><?php endforeach; ?>
      <?php foreach ($children[$top['id']] ?? [] as $sub): ?>
      <div style="margin:8px 0 0 10px;padding-left:10px;border-left:2px solid var(--line)"><span><?= e($sub['name']) ?></span>
        <?php foreach ($byRegion[$sub['name']] ?? [] as $m): ?><div class="small muted"><?= e($m['management_role']) ?>: <strong><?= e($m['user_name'] ?: $m['user_email']) ?></strong><?= $m['city'] ? ' · ' . e($m['city']) : '' ?></div><?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if (!empty($byRole['Online Team Leader']) || !empty($byRole['Online Volunteer Manager'])): ?>
<div class="card"><div class="card-head"><h2>Online teams</h2></div><ul class="check-list"><?php foreach (array_merge($byRole['Online Volunteer Manager'] ?? [], $byRole['Online Team Leader'] ?? []) as $m): ?><li><strong><?= e($m['user_name'] ?: $m['user_email']) ?></strong> · <?= e($m['management_role']) ?><?= $m['platform'] ? ' · ' . e($m['platform']) : '' ?><?= $m['language'] ? ' · ' . e($m['language']) : '' ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($tab === 'regions'): ?>
<div class="two-col">
  <div class="card">
    <div class="card-head"><h2>Regions</h2><span class="grow"></span>
      <?php if (!$tops): ?><form method="post" action="<?= e(url('/admin/hierarchy/regions')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="seed"><button class="btn btn-sm btn-primary">Create default regions</button></form><?php endif; ?>
      <a class="btn btn-sm" href="<?= e(url('/admin/hierarchy?tab=regions&region=new')) ?>"><?= icon('plus', '', 14) ?> New region</a>
    </div>
    <?php if (!$regions): ?><p class="empty">No regions yet.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Region</th><th>Branch</th><th>Sub-regions</th><th></th></tr></thead><tbody>
    <?php foreach ($tops as $top): ?>
      <tr><td><strong><?= e(($top['emoji'] ? $top['emoji'] . ' ' : '') . $top['name']) ?></strong></td><td><?= e($top['branch']) ?></td><td class="small"><?= e(implode(', ', array_column($children[$top['id']] ?? [], 'name')) ?: '—') ?></td>
        <td><div class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/hierarchy?tab=regions&region=' . $top['id'])) ?>">Edit</a><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/hierarchy?tab=regions&region=new&parent=' . $top['id'])) ?>">Add sub-region</a>
          <form method="post" action="<?= e(url('/admin/hierarchy/regions')) ?>" data-confirm="Delete this region and its sub-regions?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($top['id']) ?>"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form></div></td></tr>
      <?php foreach ($children[$top['id']] ?? [] as $sub): ?>
      <tr><td style="padding-left:32px"><?= e($sub['name']) ?></td><td class="muted"><?= e($sub['branch']) ?></td><td></td><td><div class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/hierarchy?tab=regions&region=' . $sub['id'])) ?>">Edit</a><form method="post" action="<?= e(url('/admin/hierarchy/regions')) ?>" data-confirm="Delete this sub-region?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($sub['id']) ?>"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form></div></td></tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
  <?php if ($rr || isset($_GET['region'])): ?>
  <div class="card">
    <h2><?= $rr ? 'Edit region' : ($parent ? 'New sub-region' : 'New region') ?></h2>
    <form method="post" action="<?= e(url('/admin/hierarchy/regions')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($rr['id'] ?? '') ?>">
      <div class="field"><label>Name</label><input name="name" value="<?= e($rr['name'] ?? '') ?>" required></div>
      <div class="field"><label>Emoji / flag (optional)</label><input name="emoji" value="<?= e($rr['emoji'] ?? '') ?>" maxlength="10" style="max-width:120px"></div>
      <div class="field"><label>Parent region</label><select name="parent_id"><option value="">None (top level)</option><?php foreach ($tops as $t): ?><option value="<?= e($t['id']) ?>" <?= ($rr['parent_id'] ?? $parent) === $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Branch</label><select name="branch"><?php foreach (['Global', 'UK'] as $b): ?><option <?= ($rr['branch'] ?? 'Global') === $b ? 'selected' : '' ?>><?= $b ?></option><?php endforeach; ?></select><div class="field-hint">Sub-regions inherit the parent's branch.</div></div>
      <div class="actions"><button class="btn btn-primary">Save region</button><a class="btn btn-ghost" href="<?= e(url('/admin/hierarchy?tab=regions')) ?>">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="two-col">
  <div class="card">
    <div class="card-head"><h2>Managers &amp; coordinators</h2><span class="grow"></span>
      <form class="table-toolbar" style="margin:0" method="get" action="<?= e(url('/admin/hierarchy')) ?>"><input type="hidden" name="tab" value="assignments"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search…"><button class="btn btn-sm btn-dark">Search</button></form>
      <a class="btn btn-sm" href="<?= e(url('/admin/hierarchy?tab=assignments&assignment=new')) ?>"><?= icon('plus', '', 14) ?> Assign</a>
    </div>
    <?php if (!$assignments): ?><p class="empty">Nobody has been assigned a management role yet.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Person</th><th>Role</th><th>Scope</th><th>Active</th><th></th></tr></thead><tbody>
    <?php foreach ($assignments as $m): ?>
      <tr><td><strong><?= e($m['user_name'] ?: $m['user_email']) ?></strong><br><span class="small muted"><?= e($m['user_email']) ?></span></td><td><?= e($m['management_role']) ?><br><span class="small muted"><?= e($m['volunteer_category']) ?></span></td><td class="small"><?= e(implode(' · ', array_filter([$m['region_name'], $m['country'], $m['city'], $m['platform'], $m['language']]))) ?: '—' ?></td><td><?= (int) $m['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Inactive</span>' ?></td>
        <td><div class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/hierarchy?tab=assignments&assignment=' . $m['id'])) ?>">Edit</a><form method="post" action="<?= e(url('/admin/hierarchy/assignments')) ?>" data-confirm="Remove this assignment?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($m['id']) ?>"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form></div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
  <?php if ($ra || isset($_GET['assignment'])): ?>
  <div class="card">
    <h2><?= $ra ? 'Edit assignment' : 'Assign a management role' ?></h2>
    <form method="post" action="<?= e(url('/admin/hierarchy/assignments')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($ra['id'] ?? '') ?>">
      <div class="grid-2">
        <div class="field"><label>Email</label><input type="email" name="user_email" value="<?= e($ra['user_email'] ?? '') ?>" required><div class="field-hint">If a user account exists, they get the Manager role.</div></div>
        <div class="field"><label>Name</label><input name="user_name" value="<?= e($ra['user_name'] ?? '') ?>"></div>
        <div class="field"><label>Management role</label><select name="management_role"><?php foreach (AdminReview::MANAGEMENT_ROLES as $role => $cat): ?><option <?= ($ra['management_role'] ?? '') === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Region</label><select name="region_name"><option value="">—</option><?php foreach ($regions as $r): ?><option <?= ($ra['region_name'] ?? '') === $r['name'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Country</label><input name="country" value="<?= e($ra['country'] ?? '') ?>"></div>
        <div class="field"><label>City</label><input name="city" value="<?= e($ra['city'] ?? '') ?>"></div>
        <div class="field"><label>Online platform / team</label><select name="platform"><option value="">—</option><?php foreach (AdminReview::ONLINE_PLATFORMS as $p): ?><option <?= ($ra['platform'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Language</label><input name="language" value="<?= e($ra['language'] ?? '') ?>"></div>
      </div>
      <div class="field"><label>Notes</label><textarea name="notes" rows="2"><?= e($ra['notes'] ?? '') ?></textarea></div>
      <label class="check" style="margin-bottom:12px"><input type="checkbox" name="is_active" value="1" <?= (int) ($ra['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
      <div class="actions"><button class="btn btn-primary">Save assignment</button><a class="btn btn-ghost" href="<?= e(url('/admin/hierarchy?tab=assignments')) ?>">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
