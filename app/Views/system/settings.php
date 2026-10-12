<?php $title = 'Settings'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'settings']) ?>
<form method="post" action="<?= e(url('/system/settings')) ?>" class="card-stack"><?= csrf_field() ?>
  <?php foreach ($groups as $group => $items): ?>
  <div class="card"><h2><?= e($group) ?></h2>
    <?php foreach ($items as $s): ?>
      <div class="field">
        <?php if ($s['type'] === 'bool'): ?><label class="check"><input type="checkbox" name="<?= e($s['key']) ?>" value="1" <?= in_array($s['value'], ['1', 'true', 'on'], true) ? 'checked' : '' ?>> <span><?= e($s['label']) ?></span></label>
        <?php elseif ($s['type'] === 'textarea'): ?><label><?= e($s['label']) ?></label><textarea name="<?= e($s['key']) ?>" rows="3"><?= e($s['value']) ?></textarea>
        <?php else: ?><label><?= e($s['label']) ?></label><input type="<?= $s['type'] === 'number' ? 'number' : ($s['type'] === 'email' ? 'email' : 'text') ?>" name="<?= e($s['key']) ?>" value="<?= e($s['value']) ?>"><?php endif; ?>
        <?php if ($s['help']): ?><p class="field-hint"><?= e($s['help']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <div class="form-actions"><button class="btn btn-primary btn-lg">Save settings</button></div>
</form>
<p class="muted small">Secrets (database, mail credentials, Stripe, Microsoft) live in <code>config/config.php</code> on the server and are never editable from the browser.</p>
