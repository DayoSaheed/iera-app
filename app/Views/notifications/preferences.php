<?php $title = 'Notification preferences'; $active = 'notifications'; $breadcrumbs = ['Notifications' => '/notifications', $title => null]; ?>
<div class="card" style="max-width:720px">
  <p class="muted">Choose how you want to hear about each kind of activity. Sign-in codes and account security emails are always sent.</p>
  <form method="post" action="<?= e(url('/notifications/preferences')) ?>"><?= csrf_field() ?>
    <table class="table"><thead><tr><th>Category</th><th style="width:120px">In app</th><th style="width:120px">Email</th></tr></thead><tbody>
    <?php foreach ($prefs as $k => $p): ?>
      <tr><td><strong><?= e($p['label']) ?></strong></td>
        <td><label class="check"><input type="checkbox" name="in_app_<?= e($k) ?>" value="1" <?= $p['in_app'] ? 'checked' : '' ?>><span class="small">On</span></label></td>
        <td><label class="check"><input type="checkbox" name="email_<?= e($k) ?>" value="1" <?= $p['email'] ? 'checked' : '' ?>><span class="small">On</span></label></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <div class="form-actions"><button class="btn btn-primary">Save preferences</button></div>
  </form>
</div>
