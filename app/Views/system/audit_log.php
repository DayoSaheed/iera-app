<?php $title = 'Audit log'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'audit-log']) ?>
<div class="card">
  <form method="get" class="actions" style="margin-bottom:12px"><input type="search" class="input" name="q" value="<?= e($q) ?>" placeholder="Search action, entity, details or actor…"><button class="btn btn-sm">Search</button></form>
  <div class="table-wrap"><table class="data-table" data-table data-page-size="25" data-export="audit.csv"><thead><tr><th>When</th><th data-filter>Actor</th><th data-filter>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td data-value="<?= e($r['created_at']) ?>"><?= e(fmt_datetime($r['created_at'])) ?></td><td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ($r['email'] ?? 'system')) ?></td><td><code><?= e($r['action']) ?></code></td><td><?= e($r['entity_type']) ?> <small class="muted"><?= e($r['entity_id']) ?></small></td><td class="small"><?= e(\App\Core\Str::limit((string) $r['details'], 120)) ?></td><td class="small muted"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
