<?= \App\Core\View::partial('setup/_steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Welcome to the iERA App setup</h1>
<p class="muted">This wizard checks the server, connects the database, and creates your administrator account. It disappears once setup is complete.</p>
<ul class="check-list">
<?php foreach ($checks as [$label, $ok, $detail]): ?>
  <li><span><?= e($label) ?></span><span class="<?= $ok ? 'yes' : 'no' ?>"><?= $ok ? icon('check-circle', '', 16) : icon('x-circle', '', 16) ?> <?= e($detail) ?></span></li>
<?php endforeach; ?>
</ul>
<div class="form-actions" style="justify-content:space-between">
  <a class="btn btn-ghost" href="<?= e(url('/setup/requirements')) ?>"><?= icon('refresh', '', 16) ?> Re-check</a>
  <a class="btn btn-primary <?= $ok ? '' : 'disabled' ?>" href="<?= e(url('/setup/database')) ?>" <?= $ok ? '' : 'aria-disabled="true" onclick="return false"' ?>>Continue</a>
</div>
