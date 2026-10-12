<?php $title = 'Email template: ' . $t['name']; $active = 'system'; $breadcrumbs = ['System Admin' => '/system', 'Email templates' => '/system/email-templates', $t['name'] => null]; ?>
<div class="grid-2">
  <div class="card">
    <form method="post" action="<?= e(url('/system/email-templates/' . $t['tpl_key'])) ?>" class="stack"><?= csrf_field() ?>
      <div class="field"><label>Subject</label><input type="text" name="subject" value="<?= e($t['subject']) ?>"></div>
      <div class="field"><label>Body (HTML)</label><textarea name="body_html" class="mono-editor" rows="14"><?= e($t['body_html']) ?></textarea></div>
      <div class="field"><label>SendGrid dynamic template ID (optional, used only with the sendgrid mail driver)</label><input type="text" name="sendgrid_template_id" value="<?= e($t['sendgrid_template_id']) ?>" placeholder="d-..."></div>
      <label class="check"><input type="checkbox" name="enabled" value="1" <?= (int) $t['enabled'] ? 'checked' : '' ?>> <span>Enabled (unchecked = this email is never sent)</span></label>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/system/email-templates')) ?>">Back</a><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
  <div class="card-stack">
    <div class="card"><h3>Placeholders</h3><p class="small"><?= e($t['description']) ?></p>
      <p class="small">Available: <?php foreach (array_filter(array_map('trim', explode(',', (string) $t['variables']))) as $v): ?><code>{{<?= e($v) ?>}}</code> <?php endforeach; ?> plus <code>{{app_name}}</code> <code>{{app_url}}</code> <code>{{org_name}}</code> <code>{{support_email}}</code>.</p>
      <p class="small muted">Values are HTML-escaped automatically. The body is wrapped in the standard branded layout.</p></div>
    <div class="card"><h3>Send a test</h3><p class="small muted">Sends this template to your own address with placeholder values. Mail driver: <code><?= e($driver) ?></code>.</p>
      <form method="post" action="<?= e(url('/system/email-templates/' . $t['tpl_key'] . '/test')) ?>"><?= csrf_field() ?><button class="btn">Send test email</button></form></div>
  </div>
</div>
