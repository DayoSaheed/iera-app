<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Your email address</h1>
<p class="muted"><?php if (!empty($state['category'])): ?>Registering as <strong><?= e($categories[$state['category']][0] ?? 'Volunteer') ?></strong><?php else: ?>Sign in to your existing account<?php endif; ?> · <a href="<?= e(url('/login/restart')) ?>">change</a></p>
<form method="post" action="<?= e(url('/login/email')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required">
    <label for="email">Email <span class="req">*</span></label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email" placeholder="you@example.com">
    <p class="field-hint"><?= !empty($state['category']) ? 'We will create your account and email you a verification code.' : 'We will email you a 6-digit code, or ask for your password if you set one.' ?></p>
  </div>
  <button class="btn btn-primary btn-block btn-lg">Continue</button>
</form>
