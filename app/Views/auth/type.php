<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Welcome</h1>
<p class="muted">New here? Choose the category that fits you to register. Everyone signs in with their email.</p>
<form method="post" action="<?= e(url('/login/type')) ?>" class="category-grid">
  <?= csrf_field() ?>
  <?php foreach ($categories as $key => [$label, $desc, $ico, $type]): ?>
  <button class="category-tile <?= ($selected ?? '') === $key ? 'is-selected' : '' ?> cat-<?= e($type) ?>" name="category" value="<?= e($key) ?>">
    <span class="tile-icon"><?= icon($ico, '', 26) ?></span>
    <span class="tile-text"><span class="tile-title"><?= e($label) ?></span><span class="tile-sub"><?= e($desc) ?></span></span>
    <?= icon('chevron-right', 'tile-arrow', 18) ?>
  </button>
  <?php endforeach; ?>
</form>
<div class="auth-divider"><span>Already registered?</span></div>
<form method="post" action="<?= e(url('/login/type')) ?>"><?= csrf_field() ?><button class="btn btn-dark btn-block btn-lg" name="category" value="signin"><?= icon('log-out', '', 18) ?> Sign in with your email</button></form>
<p class="auth-help">Admins and team leads also sign in with their email.</p>
