<?php $title = 'Form templates'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'form-templates']) ?>
<div class="card"><p class="small muted">These definitions drive the registration forms, event forms and report forms. Edit them carefully; field names are stored with every submission.</p>
<table class="table"><thead><tr><th>ID</th><th>Label</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach ($templates as $t): ?><tr data-href="<?= e(url('/system/form-templates/' . $t['id'])) ?>" class="row-link"><td><code><?= e($t['id']) ?></code></td><td><?= e($t['label']) ?></td><td><?= e(fmt_datetime($t['updated_at'])) ?></td><td><a class="btn btn-sm" href="<?= e(url('/system/form-templates/' . $t['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
