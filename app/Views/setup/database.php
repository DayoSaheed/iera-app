<?= \App\Core\View::partial('setup/_steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Database</h1>
<?php $pending = $_SESSION['setup_config_contents'] ?? null; ?>
<div class="<?= $status['ok'] ? 'flash flash-success' : 'flash flash-warning' ?>"><?= e($status['message']) ?></div>
<?php if ($pending): unset($_SESSION['setup_config_contents']); ?>
  <p class="small">Create <code>config/config.php</code> with this content, then click <strong>Re-check</strong>:</p>
  <div class="pre"><?= e($pending) ?></div>
<?php endif; ?>
<?php if (!$status['ok'] && str_starts_with($status['message'], 'Cannot connect')): ?>
  <p class="small muted"><?= $hasConfig ? 'The details in config/config.php do not work. Fix them in the file, or enter working details below.' : 'No config/config.php was found. Enter the database details below and the wizard will create the file.' ?></p>
  <form method="post" action="<?= e(url('/setup/database')) ?>" class="stack"><?= csrf_field() ?>
    <div class="grid-2"><div class="field is-required"><label>Database host</label><input type="text" name="host" value="<?= e($db['host']) ?>" required></div><div class="field"><label>Port</label><input type="number" name="port" value="<?= e($db['port']) ?>"></div></div>
    <div class="field is-required"><label>Database name</label><input type="text" name="name" value="<?= e($db['name']) ?>" required placeholder="cpuser_iera"></div>
    <div class="grid-2"><div class="field is-required"><label>Database user</label><input type="text" name="user" value="<?= e($db['user']) ?>" required></div><div class="field"><label>Password</label><input type="password" name="pass" autocomplete="new-password"></div></div>
    <button class="btn btn-primary">Test and save connection</button>
  </form>
<?php elseif (!$status['ok']): ?>
  <p class="small">The connection works but the tables are not there yet. Either let the wizard create them, or import <code>sql/schema.sql</code> yourself with phpMyAdmin and click Re-check.</p>
  <form method="post" action="<?= e(url('/setup/database/create-tables')) ?>" class="actions"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('database', '', 16) ?> Create the tables now</button><a class="btn btn-ghost" href="<?= e(url('/setup/database')) ?>"><?= icon('refresh', '', 16) ?> Re-check</a></form>
<?php endif; ?>
<div class="form-actions" style="justify-content:space-between">
  <a class="btn btn-ghost" href="<?= e(url('/setup/requirements')) ?>">Back</a>
  <?php if ($status['ok']): ?><a class="btn btn-primary" href="<?= e(url('/setup/application')) ?>">Continue</a><?php else: ?><a class="btn btn-ghost" href="<?= e(url('/setup/database')) ?>"><?= icon('refresh', '', 16) ?> Re-check</a><?php endif; ?>
</div>
