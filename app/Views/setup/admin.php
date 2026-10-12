<?= \App\Core\View::partial('setup/_steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Administrator account</h1>
<p class="muted">This account is the first <strong>superadmin</strong> and <strong>system admin</strong>. You will sign in with this email and password (emailed codes also work once email delivery is configured).</p>
<form method="post" action="<?= e(url('/setup/admin')) ?>" class="stack"><?= csrf_field() ?>
  <div class="grid-2"><div class="field is-required"><label>First name</label><input type="text" name="first_name" required autofocus></div><div class="field"><label>Last name</label><input type="text" name="last_name"></div></div>
  <div class="field is-required"><label>Email</label><input type="email" name="email" required autocomplete="username"></div>
  <div class="grid-2">
    <div class="field is-required"><label>Password (10+ characters)</label><input type="password" name="password" minlength="10" required autocomplete="new-password"></div>
    <div class="field is-required"><label>Repeat password</label><input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></div>
  </div>
  <div class="form-actions" style="justify-content:space-between"><a class="btn btn-ghost" href="<?= e(url('/setup/application')) ?>">Back</a><button class="btn btn-primary btn-lg">Create account and finish</button></div>
</form>
