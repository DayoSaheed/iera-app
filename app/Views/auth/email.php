<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Your email address</h1>
<p class="muted">Signing in as <strong><?= e($state['type'] === 'newmuslim' ? 'New Muslim' : 'Volunteer') ?></strong> · <a href="<?= e(url('/login/restart')) ?>">change</a></p>
<form method="post" action="<?= e(url('/login/email')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field is-required">
    <label for="email">Email <span class="req">*</span></label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email" placeholder="you@example.com">
    <p class="field-hint">New here? Enter your email and we will create your account in the next step.</p>
  </div>
  <button class="btn btn-primary btn-block btn-lg">Continue</button>
</form>
