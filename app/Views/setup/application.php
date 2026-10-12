<?= \App\Core\View::partial('setup/_steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Application</h1>
<p class="muted">Basic details. Email delivery, integrations and everything else are configured later in System Admin → Settings.</p>
<form method="post" action="<?= e(url('/setup/application')) ?>" class="stack"><?= csrf_field() ?>
  <div class="grid-2">
    <div class="field is-required"><label>Application name</label><input type="text" name="app_name" value="<?= e($app['name']) ?>" required></div>
    <div class="field is-required"><label>Organisation name</label><input type="text" name="org_name" value="<?= e($org['org_name']) ?>" required></div>
  </div>
  <div class="field is-required"><label>Application URL</label><input type="url" name="app_url" value="<?= e($app['url']) ?>" required><p class="field-hint">Detected from this request. Use the https:// address people will type.</p></div>
  <div class="field"><label>Time zone</label><select name="timezone"><?php foreach ($timezones as $tz): ?><option value="<?= e($tz) ?>" <?= $tz === $app['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Support / contact email</label><input type="email" name="support_email" value="<?= e($org['support_email']) ?>"><p class="field-hint">Shown in the footer and used as the reply-to and default sender address.</p></div>
  <div class="field"><label>Admin notification emails</label><textarea name="admin_notification_emails" rows="2"><?= e($org['admin_notification_emails']) ?></textarea><p class="field-hint">One per line. Receives new registration alerts.</p></div>
  <?php if (!$configWritable): ?><div class="flash flash-warning">The config folder is not writable, so the wizard will show you the file contents to paste manually.</div><?php endif; ?>
  <div class="form-actions" style="justify-content:space-between"><a class="btn btn-ghost" href="<?= e(url('/setup/database')) ?>">Back</a><button class="btn btn-primary">Continue</button></div>
</form>
