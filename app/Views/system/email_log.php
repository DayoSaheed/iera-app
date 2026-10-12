<?php $title = 'Email log'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'email-log']) ?>
<div class="card">
  <form method="get" class="actions" style="margin-bottom:12px"><input type="search" class="input" name="q" value="<?= e($q) ?>" placeholder="Search recipient, subject or template…"><button class="btn btn-sm">Search</button></form>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-empty="No emails logged."><thead><tr><th>Sent</th><th>To</th><th>Subject</th><th data-filter>Template</th><th data-filter>Status</th><th>Error</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr class="<?= $r['status'] === 'failed' ? 'row-red' : '' ?>"><td data-value="<?= e($r['created_at']) ?>"><?= e(fmt_datetime($r['created_at'])) ?></td><td><?= e($r['to_email']) ?></td><td><?= e($r['subject']) ?></td><td><?= e($r['template_key']) ?></td><td><span class="badge badge-<?= $r['status'] === 'sent' ? 'ok' : 'failed' ?>"><?= e($r['status']) ?></span></td><td class="small"><?= e($r['error']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
