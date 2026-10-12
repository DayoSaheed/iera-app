<?php $title = 'My Profile'; $active = 'profile'; ?>
<div class="grid-2">
  <div class="card">
    <h2>Personal details</h2>
    <p class="muted small">Signed in as <strong><?= e($u['email']) ?></strong> · <?= e(ucfirst((string) $u['type'])) ?> · <span class="badge badge-<?= e($u['status']) ?>"><?= e(\App\Models\User::statusLabel($u)) ?></span></p>
    <form method="post" action="<?= e(url('/profile')) ?>" enctype="multipart/form-data" novalidate><?= csrf_field() ?><?= $formHtml ?><div class="form-actions"><button class="btn btn-primary">Save profile</button></div></form>
  </div>
  <div class="card-stack">
    <?php if ($teams): ?><div class="card"><h3>My teams</h3><?php foreach ($teams as $t): ?><span class="chip"><?= e($t['name']) ?><?= in_array($t['id'], $u['admin_teams'], true) ? ' (lead)' : '' ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($documents): ?><div class="card"><h3>My documents</h3><ul class="check-list"><?php foreach ($documents as $d): ?><li><a href="<?= e(url('/documents/' . $d['id'])) ?>" target="_blank" rel="noopener"><?= e($d['filename']) ?></a><form method="post" action="<?= e(url('/documents/' . $d['id'] . '/delete')) ?>" data-confirm="Delete this document?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Delete</button></form></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($passwordEnabled): ?>
    <div class="card"><h3>Password</h3>
      <p class="muted small"><?= $u['password_hash'] ? 'You have a password. You can still sign in with an emailed code.' : 'Optional: set a password to sign in faster. Emailed codes always work.' ?></p>
      <form method="post" action="<?= e(url('/profile/password')) ?>" class="stack"><?= csrf_field() ?>
        <?php if ($u['password_hash']): ?><div class="field"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password"></div><?php endif; ?>
        <div class="field"><label>New password (10+ characters)</label><input type="password" name="password" minlength="10" autocomplete="new-password"></div>
        <div class="field"><label>Repeat new password</label><input type="password" name="password_confirmation" minlength="10" autocomplete="new-password"></div>
        <div class="actions"><button class="btn btn-primary btn-sm">Save password</button><?php if ($u['password_hash']): ?><button class="btn btn-ghost btn-sm" name="remove" value="1" formnovalidate>Remove password</button><?php endif; ?></div>
      </form>
    </div>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <div class="card"><h3>Two-step sign-in (authenticator app)</h3>
      <?php if ((int) $u['totp_enabled']): ?>
        <p class="small"><span class="yes">On.</span> Each sign-in asks for a code from your authenticator app.</p>
        <form method="post" action="<?= e(url('/profile/two-step')) ?>" data-confirm="Turn off authenticator two-step sign-in?"><?= csrf_field() ?><input type="hidden" name="action" value="disable"><button class="btn btn-sm btn-ghost">Turn off</button></form>
      <?php elseif ($totpUri): ?>
        <p class="small">Scan this with Google Authenticator, Microsoft Authenticator or similar, then enter the code shown.</p>
        <div class="pre" style="word-break:break-all"><?= e($totpUri) ?></div>
        <p class="small muted">Secret (manual entry): <code><?= e($u['totp_secret']) ?></code></p>
        <form method="post" action="<?= e(url('/profile/two-step')) ?>" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input class="input" style="max-width:160px" name="code" inputmode="numeric" placeholder="123456" required><button class="btn btn-sm btn-primary">Confirm</button><button class="btn btn-sm btn-ghost" name="action" value="disable">Cancel</button></form>
      <?php else: ?>
        <p class="small muted">Admins confirm sign-ins with an emailed code<?= \App\Models\Setting::bool('require_2fa_admins') ? '' : ' when required' ?>. For stronger protection, use an authenticator app.</p>
        <form method="post" action="<?= e(url('/profile/two-step')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="start"><button class="btn btn-sm">Set up authenticator app</button></form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
