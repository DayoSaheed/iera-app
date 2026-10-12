<?php $title = 'System Admin'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => '']) ?>
<?php if ($pendingMigrations): ?>
<div class="flash flash-warning" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-right:14px">
  <div class="grow"><strong>Database update required.</strong> This version needs <?= count($pendingMigrations) ?> database change<?= count($pendingMigrations) > 1 ? 's' : '' ?> (<?= e(implode(', ', $pendingMigrations)) ?>). Until it is applied some features, such as the email log, cannot save records.</div>
  <form method="post" action="<?= e(url('/system/migrate')) ?>"><?= csrf_field() ?><button class="btn btn-dark"><?= icon('database', '', 16) ?> Apply database updates</button></form>
</div>
<?php endif; ?>
<div class="card" style="margin-bottom:16px"><div class="card-head"><div><h2>Things to fix</h2><p class="small muted" style="margin:0">Checked every time this page opens. Most important first.</p></div></div><?= \App\Core\View::partial('partials/issues', ['issues' => $issues]) ?></div>
<div class="grid-4">
  <div class="stat primary"><div class="stat-label">Users</div><div class="stat-value"><?= $counts['users'] ?></div></div>
  <div class="stat"><div class="stat-label">Teams</div><div class="stat-value"><?= $counts['teams'] ?></div></div>
  <div class="stat"><div class="stat-label">Event occurrences</div><div class="stat-value"><?= $counts['sub_events'] ?></div></div>
  <div class="stat"><div class="stat-label">Reports</div><div class="stat-value"><?= $counts['reports'] ?></div></div>
  <div class="stat info"><div class="stat-label">Emails (24h)</div><div class="stat-value"><?= $emails24h ?></div><div class="stat-sub"><?= $emailsFailed24h ?> failed</div></div>
  <div class="stat <?= $checks['cron_recent'] ? '' : 'danger' ?>"><div class="stat-label">Scheduler</div><div class="stat-value"><?= $checks['cron_recent'] ? 'OK' : 'Stale' ?></div><div class="stat-sub">last run <?= e($lastCron ? fmt_datetime($lastCron) : 'never') ?></div></div>
</div>
<div class="grid-2" style="margin-top:16px">
  <div class="card"><h2>Health checks</h2>
    <ul class="check-list">
      <li>config/config.php present <span class="<?= $checks['config_file'] ? 'yes' : 'no' ?>"><?= $checks['config_file'] ? 'Yes' : 'No (using sample)' ?></span></li>
      <li>Storage folders writable <span class="<?= $checks['storage_writable'] ? 'yes' : 'no' ?>"><?= $checks['storage_writable'] ? 'Yes' : 'No' ?></span></li>
      <li>Application key set <span class="<?= $checks['app_key'] ? 'yes' : 'no' ?>"><?= $checks['app_key'] ? 'Yes' : 'Change it!' ?></span></li>
      <li>Debug mode off <span class="<?= $checks['debug_off'] ? 'yes' : 'no' ?>"><?= $checks['debug_off'] ? 'Yes' : 'No' ?></span></li>
      <li>HTTPS app URL <span class="<?= $checks['https'] ? 'yes' : 'no' ?>"><?= $checks['https'] ? 'Yes' : 'No' ?></span></li>
      <li>Mail driver <span><?= e($checks['mail_driver']) ?></span></li>
      <li>Cron ran in the last 15 minutes <span class="<?= $checks['cron_recent'] ? 'yes' : 'no' ?>"><?= $checks['cron_recent'] ? 'Yes' : 'No' ?></span></li>
      <li>PHP / database <span><?= e($checks['php']) ?> / <?= e($checks['db']) ?></span></li>
      <li>Database schema up to date <span class="<?= $pendingMigrations ? 'no' : 'yes' ?>"><?= $pendingMigrations ? count($pendingMigrations) . ' pending' : 'Yes' ?></span></li>
    </ul>
  </div>
  <div class="card-stack">
    <div class="card"><h2>Maintenance mode</h2><p class="small muted">When on, only system admins can use the app; everyone else sees the maintenance message (Settings → System).</p>
      <form method="post" action="<?= e(url('/system/maintenance')) ?>"><?= csrf_field() ?><input type="hidden" name="on" value="<?= $maintenance ? '0' : '1' ?>"><button class="btn <?= $maintenance ? 'btn-primary' : 'btn-danger' ?>"><?= $maintenance ? 'Turn maintenance mode off' : 'Turn maintenance mode on' ?></button></form></div>
    <div class="card"><h2>Backup and defaults</h2>
      <div class="actions"><a class="btn" href="<?= e(url('/system/backup')) ?>"><?= icon('download', '', 16) ?> Download SQL backup</a>
        <form method="post" action="<?= e(url('/system/seed')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost">Load missing defaults</button></form>
        <form method="post" action="<?= e(url('/system/seed')) ?>" class="inline" data-confirm="Overwrite ALL settings, email templates and form templates with the defaults?"><?= csrf_field() ?><input type="hidden" name="overwrite" value="1"><button class="btn btn-ghost">Reset all to defaults</button></form></div>
      <p class="small muted" style="margin-top:8px">cPanel backups remain the primary backup. This download is a convenience export of all tables.</p></div>
  </div>
</div>
<div class="card" style="margin-top:16px"><h2>Scheduled jobs</h2>
  <table class="table"><thead><tr><th>Job</th><th>Last run</th><th>Status</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($jobs as $k => $j): $l = $j['last']; ?><tr><td><?= e($j['label']) ?></td><td><?= e($l ? fmt_datetime($l['started_at']) : 'never') ?></td><td><?= $l ? '<span class="badge badge-' . e($l['status']) . '">' . e($l['status']) . '</span>' : '' ?></td><td class="small muted"><?= e($l['message'] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div>
