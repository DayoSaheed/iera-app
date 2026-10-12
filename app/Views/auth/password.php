<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Enter your password</h1>
<p class="muted"><?= e($email) ?> · <a href="<?= e(url('/login/email')) ?>">not you?</a></p>
<form method="post" action="<?= e(url('/login/password')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required">
    <label for="password">Password <span class="req">*</span></label>
    <input id="password" type="password" name="password" required autofocus autocomplete="current-password">
  </div>
  <label class="check"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in on this device for 30 days</span></label>
  <button class="btn btn-primary btn-block btn-lg">Sign in</button>
</form>
<div class="auth-alt">
  <form method="post" action="<?= e(url('/login/send-code')) ?>"><?= csrf_field() ?><button class="link">Email me a one-time code instead</button></form>
  <a href="<?= e(url('/password/forgot')) ?>">Forgot your password?</a>
</div>
