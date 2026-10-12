<?php $title = 'Email templates'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'email-templates']) ?>
<div class="card"><table class="table"><thead><tr><th>Template</th><th>Subject</th><th>SendGrid ID</th><th>Enabled</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach ($templates as $t): ?><tr data-href="<?= e(url('/system/email-templates/' . $t['tpl_key'])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong><br><small class="muted"><?= e($t['description']) ?></small></td><td><?= e($t['subject']) ?></td><td><code><?= e($t['sendgrid_template_id'] ?: '-') ?></code></td><td><?= (int) $t['enabled'] ? '<span class="yes">Yes</span>' : '<span class="no">No</span>' ?></td><td><?= e(fmt_date($t['updated_at'])) ?></td><td><a class="btn btn-sm" href="<?= e(url('/system/email-templates/' . $t['tpl_key'])) ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
