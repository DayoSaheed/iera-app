<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Two-step confirmation</h1>
<p class="muted"><?php if ($method === 'totp'): ?>Open your authenticator app and enter the current 6-digit code.<?php else: ?>As an admin, please confirm with the code we just emailed to <strong><?= e($email) ?></strong>.<?php endif; ?></p>
<form method="post" action="<?= e(url('/login/two-step')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required">
    <label for="code">Code <span class="req">*</span></label>
    <input id="code" class="code-input" type="text" name="code" inputmode="numeric" maxlength="7" required autofocus autocomplete="one-time-code">
  </div>
  <button class="btn btn-primary btn-block btn-lg">Confirm</button>
</form>
<p class="auth-help"><a href="<?= e(url('/login/restart')) ?>">Start over</a></p>
