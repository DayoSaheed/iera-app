<h1 class="auth-title">Reset your password</h1>
<p class="muted">Enter your email and we will send a reset link. You can always sign in with an emailed code instead.</p>
<form method="post" action="<?= e(url('/password/forgot')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required"><label for="email">Email</label><input id="email" type="email" name="email" required autofocus></div>
  <button class="btn btn-primary btn-block">Send reset link</button>
</form>
<p class="auth-help"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
