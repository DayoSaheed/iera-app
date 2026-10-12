<?php $title = 'Admins'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'admins']) ?>
<div class="grid-2">
  <div class="card"><h2>Admin accounts</h2>
    <p class="small muted"><strong>Superadmin</strong> manages volunteers, New Muslims, teams and analytics. <strong>System admin</strong> additionally manages this control room (settings, templates, logs, jobs, backups).</p>
    <table class="table"><thead><tr><th>Name</th><th>Email</th><th>Superadmin</th><th>System admin</th><th>2-step app</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= e(\App\Models\User::fullName($r)) ?></td><td><?= e($r['email']) ?></td><td><?= $r['type'] === 'superadmin' ? '<span class="yes">Yes</span>' : '' ?></td><td><?= (int) $r['is_sysadmin'] ? '<span class="yes">Yes</span>' : '' ?></td><td><?= (int) $r['totp_enabled'] ? '<span class="yes">On</span>' : '<span class="muted">Off</span>' ?></td>
      <td class="actions"><form method="post" action="<?= e(url('/system/admins')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_sysadmin"><input type="hidden" name="user_id" value="<?= e($r['id']) ?>"><button class="btn btn-sm btn-ghost"><?= (int) $r['is_sysadmin'] ? 'Remove sysadmin' : 'Make sysadmin' ?></button></form>
        <?php if ((int) $r['totp_enabled']): ?><form method="post" action="<?= e(url('/system/admins')) ?>" class="inline" data-confirm="Reset the authenticator app for this admin?"><?= csrf_field() ?><input type="hidden" name="action" value="reset_2fa"><input type="hidden" name="user_id" value="<?= e($r['id']) ?>"><button class="btn btn-sm btn-ghost">Reset 2-step</button></form><?php endif; ?>
        <form method="post" action="<?= e(url('/system/admins')) ?>" class="inline" data-confirm="Revoke all admin access for <?= e($r['email']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="user_id" value="<?= e($r['id']) ?>"><button class="btn btn-sm btn-danger">Revoke</button></form></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card"><h2>Grant admin access</h2>
    <form method="post" action="<?= e(url('/system/admins')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="field is-required"><label>Email <span class="req">*</span></label><input type="email" name="email" required><p class="field-hint">An existing account is promoted; otherwise a new superadmin account is created. They sign in with their email code.</p></div>
      <div class="grid-2"><div class="field"><label>First name</label><input type="text" name="first_name"></div><div class="field"><label>Last name</label><input type="text" name="last_name"></div></div>
      <label class="check"><input type="checkbox" name="sysadmin" value="1"> <span>Also a system admin</span></label>
      <button class="btn btn-primary">Grant access</button>
    </form>
  </div>
</div>
