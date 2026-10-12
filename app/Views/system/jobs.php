<?php $title = 'Scheduled jobs'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'jobs']) ?>
<div class="card">
  <div class="help-box"><strong>Three ways to run the jobs.</strong><br>
    1. cPanel cron (recommended), every 5 minutes: <code>*/5 * * * * <?= e($phpBinary) ?> <?= e(BASE_PATH) ?>/cron/run.php >/dev/null 2>&1</code><br>
    2. Manually from this page: the <em>Run now</em> buttons, or <em>Run all jobs now</em>.<br>
    3. From any external monitor or cron service<?= $cronSecret ? ': <code>' . e(url('/cron/run?key=')) . '&lt;scheduler web key&gt;</code>' : ' (set a scheduler web key in Settings → System first)' ?>.
  </div>
  <form method="post" action="<?= e(url('/system/jobs/run-all')) ?>" style="margin-top:12px" data-confirm="Run every job now (ignoring their normal schedule)?"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('play', '', 16) ?> Run all jobs now</button></form>
  <table class="table" style="margin-top:14px"><thead><tr><th>Job</th><th>Description</th><th>Last run</th><th>Result</th><th></th></tr></thead><tbody>
  <?php foreach ($jobs as $j): $l = $j['last']; ?><tr><td><strong><?= e($j['label']) ?></strong><br><code class="small"><?= e($j['key']) ?></code></td><td class="small"><?= e($j['description']) ?></td><td><?= e($l ? fmt_datetime($l['started_at']) : 'never') ?><?= $l ? '<br><span class="badge badge-' . e($l['status']) . '">' . e($l['status']) . '</span>' : '' ?></td><td class="small muted"><?= e($l['message'] ?? '') ?></td><td><form method="post" action="<?= e(url('/system/jobs/run')) ?>" data-confirm="Run '<?= e($j['label']) ?>' now?"><?= csrf_field() ?><input type="hidden" name="job" value="<?= e($j['key']) ?>"><button class="btn btn-sm">Run now</button></form></td></tr><?php endforeach; ?>
  </tbody></table>
</div>
<div class="card"><h2>Recent runs</h2>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="20"><thead><tr><th>Started</th><th data-filter>Job</th><th>Period</th><th data-filter>Status</th><th>Message</th></tr></thead><tbody>
  <?php foreach ($runs as $r): ?><tr class="<?= $r['status'] === 'failed' ? 'row-red' : '' ?>"><td data-value="<?= e($r['started_at']) ?>"><?= e(fmt_datetime($r['started_at'])) ?></td><td><?= e($r['job']) ?></td><td><?= e($r['period_key']) ?></td><td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td><td class="small"><?= e($r['message']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
