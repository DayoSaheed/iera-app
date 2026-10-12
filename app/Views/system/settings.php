<?php $title = 'Settings'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'settings']) ?>
<div class="help-box" style="margin-bottom:16px">Everything here is stored in the database and takes effect immediately. Secrets are encrypted with the application key. <code>config/config.php</code> only holds the database connection, the application URL and key.</div>
<div class="tabs" id="settingsTabs"><?php foreach (array_keys($groups) as $g): ?><a class="tab" href="#<?= e($g) ?>" data-settings-tab="<?= e($g) ?>"><?= e($g) ?></a><?php endforeach; ?></div>
<form method="post" action="<?= e(url('/system/settings')) ?>" class="card-stack" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="tab" value="">
  <?php foreach ($groups as $group => $items): ?>
  <div class="card settings-group" id="<?= e($group) ?>" data-settings-group="<?= e($group) ?>"><h2><?= e($group) ?></h2>
    <?php if ($group === 'Email'): ?><?php if (\App\Models\Setting::mail()['driver'] === 'log'): ?><div class="flash flash-warning"><strong>Emails are not being delivered.</strong> The method is <em>log</em>: messages only go to <code>storage/logs/mail.log</code>. Choose <em>smtp</em> (cPanel mailbox) or <em>sendgrid</em>, fill in the details, save, then send the test email.</div><?php endif; ?><p class="small muted">After saving, use <strong>Send test email</strong> below to confirm delivery. Sign-in codes depend on this.</p><?php endif; ?>
    <?php if ($group === 'Appearance'): ?><p class="small muted">Colours apply immediately across the app. Upload a logo and a background photo for the sign-in and registration pages.</p><?php endif; ?>
    <?php foreach ($items as $s): ?>
      <div class="field">
        <?php if ($s['type'] === 'bool'): ?><label class="check"><input type="checkbox" name="<?= e($s['key']) ?>" value="1" <?= in_array($s['value'], ['1', 'true', 'on'], true) ? 'checked' : '' ?>> <span><?= e($s['label']) ?></span></label>
        <?php elseif ($s['type'] === 'textarea'): ?><label><?= e($s['label']) ?></label><textarea name="<?= e($s['key']) ?>" rows="3"><?= e($s['value']) ?></textarea>
        <?php elseif ($s['type'] === 'secret'): ?><label><?= e($s['label']) ?> <?= $s['value'] !== '' ? '<span class="badge badge-ok">set</span>' : '<span class="badge badge-default">not set</span>' ?></label>
          <input type="password" name="<?= e($s['key']) ?>" value="" autocomplete="new-password" placeholder="<?= $s['value'] !== '' ? 'Leave blank to keep the current value' : 'Enter value' ?>">
          <?php if ($s['value'] !== ''): ?><label class="check small" style="margin-top:6px"><input type="checkbox" name="<?= e($s['key']) ?>_clear" value="1"> <span>Clear this value</span></label><?php endif; ?>
        <?php elseif ($s['type'] === 'color'): ?><label><?= e($s['label']) ?></label><div class="actions"><input type="color" name="<?= e($s['key']) ?>" value="<?= e($s['value'] ?: '#000000') ?>" style="width:64px;height:40px;padding:2px;border:1px solid var(--line);border-radius:8px;background:var(--surface)"><code><?= e($s['value']) ?></code></div>
        <?php elseif ($s['type'] === 'image'): ?><label><?= e($s['label']) ?></label>
          <?php if ($s['value'] !== ''): ?><div style="margin-bottom:8px"><?php if (\App\Models\Setting::uploadedFile($s['key']) !== ''): ?><img src="<?= e(url($s['value'])) ?>" alt="" style="max-height:120px;max-width:100%;border-radius:8px;border:1px solid var(--line);background:#222"><?php else: ?><span class="badge badge-failed">File missing on the server</span> <span class="small muted">The saved image <?= e($s['value']) ?> is no longer on disk. Upload it again or tick remove.</span><?php endif; ?></div><label class="check small" style="margin-bottom:6px"><input type="checkbox" name="<?= e($s['key']) ?>_clear" value="1"> <span>Remove this image</span></label><?php endif; ?>
          <input type="file" name="<?= e($s['key']) ?>" accept="image/*">
        <?php elseif (str_starts_with($s['type'], 'select:')): ?><label><?= e($s['label']) ?></label><select name="<?= e($s['key']) ?>"><?php foreach (explode('|', substr($s['type'], 7)) as $opt): ?><option value="<?= e($opt) ?>" <?= $s['value'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select>
        <?php else: ?><label><?= e($s['label']) ?></label><input type="<?= $s['type'] === 'number' ? 'number' : ($s['type'] === 'email' ? 'email' : 'text') ?>" name="<?= e($s['key']) ?>" value="<?= e($s['value']) ?>"><?php endif; ?>
        <?php if ($s['help']): ?><p class="field-hint"><?= e($s['help']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($group === 'Email'): ?>
      <div class="actions" style="margin-top:8px"><button class="btn btn-primary">Save settings</button><button class="btn" formaction="<?= e(url('/system/settings/test-mail')) ?>" formnovalidate><?= icon('send', '', 16) ?> Save and send test email to me</button></div>
    <?php else: ?>
      <div class="actions" style="margin-top:8px"><button class="btn btn-primary">Save settings</button></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</form>
<script>
(function(){
  var tabs=document.querySelectorAll('[data-settings-tab]'),groups=document.querySelectorAll('[data-settings-group]');
  function show(g){tabs.forEach(function(t){t.classList.toggle('is-active',t.dataset.settingsTab===g)});groups.forEach(function(x){x.hidden=x.dataset.settingsGroup!==g});document.querySelector('input[name=tab]').value=g;}
  tabs.forEach(function(t){t.addEventListener('click',function(e){e.preventDefault();show(t.dataset.settingsTab);history.replaceState(null,'','#'+t.dataset.settingsTab)})});
  var h=decodeURIComponent(location.hash.slice(1));show(h&&document.getElementById(h)?h:tabs[0].dataset.settingsTab);
})();
</script>
