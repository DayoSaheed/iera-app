<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Check your email</h1>
<p class="muted">We sent a 6-digit code to <strong><?= e($email) ?></strong> · <a href="<?= e(url('/login/email')) ?>">change</a></p>
<form method="post" action="<?= e(url('/login/code')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required">
    <label for="code">Verification code <span class="req">*</span></label>
    <input id="code" class="code-input" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus autocomplete="one-time-code" placeholder="6-digit code">
    <p class="field-hint">The code is valid for <?= \App\Models\Otp::TTL_MINUTES ?> minutes. Check your spam folder if you do not see it.</p>
  </div>
  <label class="check"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in on this device for 30 days</span></label>
  <button class="btn btn-primary btn-block btn-lg">Verify and continue</button>
</form>
<div class="auth-alt">
  <form method="post" action="<?= e(url('/login/send-code')) ?>"><?= csrf_field() ?><button class="link" data-resend>Resend the code</button></form>
</div>
