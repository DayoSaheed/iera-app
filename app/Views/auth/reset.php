<h1 class="auth-title">Choose a new password</h1>
<form method="post" action="<?= e(url('/password/reset/' . $token)) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required"><label for="password">New password (10+ characters)</label><input id="password" type="password" name="password" minlength="10" required autocomplete="new-password"></div>
  <div class="field is-required"><label for="password_confirmation">Repeat password</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></div>
  <button class="btn btn-primary btn-block">Save password</button>
</form>
