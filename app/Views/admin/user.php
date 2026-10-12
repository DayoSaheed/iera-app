<?php
$title = \App\Models\User::fullName($target); $active = 'admin';
$breadcrumbs = ['People' => $back, $title => null];
$teamNames = \App\Models\Team::names();
?>
<div class="grid" style="grid-template-columns: 2fr 1fr">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Details</h2><span class="grow"></span><span class="badge badge-<?= e($target['status']) ?>"><?= e(\App\Models\User::statusLabel($target)) ?></span></div>
      <p class="muted small"><?= e($target['email']) ?> · <?= e(ucfirst((string) $target['type'])) ?> · registered <?= e(fmt_date($target['created_at'])) ?> · last sign-in <?= e(fmt_datetime($target['last_login_at']) ?: 'never') ?><?= $target['created_by'] ? ' · referred by ' . e(\App\Models\User::names([$target['created_by']])[$target['created_by']] ?? '') : '' ?></p>
      <form method="post" action="<?= e(url('/admin/users/' . $target['id'] . ($back ? '?back=' . urlencode($back) : ''))) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php if ($isSuper): ?>
          <h3 class="form-section">Account</h3>
          <div class="grid-2">
            <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($target['email']) ?>"></div>
            <?php if ($target['type'] === 'volunteer'): ?><div class="field"><label>PIN</label><input type="text" name="pin" value="<?= e($target['pin'] ?: \App\Models\User::nextPin()) ?>"></div><?php endif; ?>
          </div>
          <?php if ($target['type'] === 'volunteer'): ?>
          <div class="field"><label>Roles</label><div class="choices"><?php foreach (\App\Models\User::SUB_GROUPS as $sg): ?><label class="choice"><input type="checkbox" name="sub_groups[]" value="<?= $sg ?>" <?= in_array($sg, $target['sub_groups'], true) ? 'checked' : '' ?>> <span><?= $sg === 'daee' ? "Da'ee (Street Dawah)" : 'Mentor (New Muslim)' ?></span></label><?php endforeach; ?></div></div>
          <div class="grid-2">
            <div class="field"><label>Teams</label><div class="choices"><?php foreach ($teams as $t): ?><label class="choice"><input type="checkbox" name="teams[]" value="<?= e($t['id']) ?>" <?= in_array($t['id'], $target['teams'], true) ? 'checked' : '' ?>> <span><?= e($t['name']) ?></span></label><?php endforeach; ?></div></div>
            <div class="field"><label>Team lead of</label><div class="choices"><?php foreach ($teams as $t): ?><label class="choice"><input type="checkbox" name="admin_teams[]" value="<?= e($t['id']) ?>" <?= in_array($t['id'], $target['admin_teams'], true) ? 'checked' : '' ?>> <span><?= e($t['name']) ?></span></label><?php endforeach; ?></div></div>
          </div>
          <?php elseif ($target['type'] === 'newmuslim'): ?>
          <div class="field"><label>Teams (optional)</label><div class="choices"><?php foreach ($teams as $t): ?><label class="choice"><input type="checkbox" name="teams[]" value="<?= e($t['id']) ?>" <?= in_array($t['id'], $target['teams'], true) ? 'checked' : '' ?>> <span><?= e($t['name']) ?></span></label><?php endforeach; ?></div></div>
          <?php endif; ?>
          <div class="grid-2"><div class="field"><label>Admin comment (shown to the user when rejected / paused)</label><textarea name="comments" rows="2"><?= e($target['comments']) ?></textarea></div><div class="field"><label>Paused until</label><input type="date" name="paused_until" value="<?= e($target['paused_until']) ?>"></div></div>
          <h3 class="form-section">Profile</h3>
        <?php endif; ?>
        <?= $formHtml ?>
        <?php if ($canEdit): ?><div class="form-actions"><a class="btn btn-ghost" href="<?= e(url($back)) ?>">Back</a><button class="btn btn-primary">Save changes</button></div><?php endif; ?>
      </form>
    </div>
    <div class="card"><h2>Comments</h2>
      <?php if ($target['id'] !== $u['id']): ?><form method="post" action="<?= e(url('/users/' . $target['id'] . '/comments')) ?>" class="stack"><?= csrf_field() ?><div class="field"><label>Add a comment (visible to admins, leads and mentors)</label><textarea name="comment" rows="2" required></textarea></div><button class="btn btn-sm btn-primary">Add comment</button></form><?php endif; ?>
      <?php if ($comments): ?><ul class="check-list" style="margin-top:12px"><?php foreach ($comments as $c): ?><li><div><?= nl2br(e($c['comment'])) ?><br><small class="muted"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['author_email'] ?? 'System')) ?> · <?= e(fmt_datetime($c['created_at'])) ?></small></div></li><?php endforeach; ?></ul><?php else: ?><p class="muted small">No comments yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="card-stack">
    <?php if ($isSuper): ?>
    <div class="card"><h3>Status</h3>
      <form method="post" action="<?= e(url('/admin/users/' . $target['id'] . '/status')) ?>" class="stack"><?= csrf_field() ?>
        <div class="field"><select name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $target['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Comment to the user (optional)</label><textarea name="comments" rows="2"></textarea></div>
        <button class="btn btn-primary btn-sm btn-block">Update status</button>
        <p class="muted small">Approving a volunteer emails them a welcome; rejecting emails the comment above.</p>
      </form>
      <?php if ($target['type'] === 'newmuslim'): ?><a class="btn btn-sm btn-block" href="<?= e(url('/mentees/' . $target['id'] . '/assign?back=' . urlencode('/admin/users/' . $target['id']))) ?>">Assign mentors</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($documents): ?><div class="card"><h3>Documents</h3><ul class="check-list"><?php foreach ($documents as $d): ?><li><a href="<?= e(url('/documents/' . $d['id'])) ?>" target="_blank" rel="noopener"><?= e($d['filename']) ?></a><small class="muted"><?= e(fmt_date($d['created_at'])) ?></small></li><?php endforeach; ?></ul>
      <?php if ($target['stripe_status']): ?><p class="small muted">Stripe ID check: <strong><?= e($target['stripe_status']) ?></strong></p><?php endif; ?></div><?php endif; ?>
    <?php if ($mentors): ?><div class="card"><h3>Mentors</h3><ul class="check-list"><?php foreach ($mentors as $a): ?><li><a href="<?= e(url('/admin/users/' . $a['mentor_id'])) ?>"><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></a><span class="badge badge-<?= $a['accepted_at'] ? 'approved' : ($a['rejected_at'] ? 'rejected' : 'pending') ?>"><?= $a['accepted_at'] ? 'accepted' : ($a['rejected_at'] ? 'declined' : 'pending') ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($mentees): ?><div class="card"><h3>Mentees</h3><ul class="check-list"><?php foreach ($mentees as $a): ?><li><a href="<?= e(url('/admin/users/' . $a['mentee_id'])) ?>"><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></a><span class="badge badge-<?= $a['accepted_at'] ? 'approved' : 'pending' ?>"><?= $a['accepted_at'] ? 'accepted' : 'pending' ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="card"><h3>Reports (<?= count($reports) ?>)</h3>
      <?php if (!$reports): ?><p class="muted small">None.</p><?php else: ?><ul class="check-list"><?php foreach (array_slice($reports, 0, 8) as $r): ?><li><a href="<?= e(url('/reports/' . $r['id'])) ?>"><?= e($r['event_name'] ?? $r['mentee_name'] ?? 'Report') ?> · <?= e(fmt_date($r['start_at'] ?? $r['created_at'])) ?></a><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
    <?php if ($vacations): ?><div class="card"><h3>Vacations</h3><ul class="check-list"><?php foreach ($vacations as $v): ?><li><?= e(fmt_date($v['from_date'])) ?> – <?= e(fmt_date($v['to_date'])) ?><span class="badge badge-<?= e($v['status']) ?>"><?= e($v['status']) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($audit): ?><div class="card"><h3>History</h3><ul class="check-list small"><?php foreach (array_slice($audit, 0, 12) as $a): ?><li><span><?= e($a['action']) ?><?= $a['details'] ? ' <span class="muted">' . e(\App\Core\Str::limit((string) $a['details'], 60)) . '</span>' : '' ?></span><small class="muted"><?= e(fmt_date($a['created_at'])) ?></small></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($isSuper && $target['id'] !== $u['id'] && $target['type'] !== 'superadmin'): ?>
    <div class="card"><h3>Danger zone</h3>
      <?php if (\App\Core\Auth::isSysadmin($u)): ?><form method="post" action="<?= e(url('/system/impersonate/' . $target['id'])) ?>" style="margin-bottom:10px"><?= csrf_field() ?><button class="btn btn-sm btn-block">Sign in as this user</button></form><?php endif; ?>
      <form method="post" action="<?= e(url('/admin/users/' . $target['id'] . '/delete')) ?>" data-confirm="Permanently delete this account and all its data?"><?= csrf_field() ?><div class="field"><label>Type the email to confirm</label><input type="text" name="confirm" placeholder="<?= e($target['email']) ?>"></div><button class="btn btn-danger btn-sm btn-block">Delete account</button></form>
    </div>
    <?php endif; ?>
  </div>
</div>
<style>@media (max-width: 1000px){ .grid[style*="2fr 1fr"]{ grid-template-columns: 1fr !important; } }</style>
