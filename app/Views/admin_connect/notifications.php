<?php use App\Services\NotificationRules; $title = 'Notifications centre'; $active = 'cc-notifications'; ?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach (['rules' => 'Routing rules', 'log' => 'Activity log', 'mine' => 'My notifications'] as $k => $l): ?><a class="tab <?= $tab === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/notifications?tab=' . $k)) ?>"><?= $l ?></a><?php endforeach; ?></div>
  <span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/notifications/preferences')) ?>"><?= icon('settings', '', 14) ?> My preferences</a>
</div>

<?php if ($tab === 'rules'): ?>
<div class="<?= $edit !== null ? 'two-col' : '' ?>">
  <div class="card">
    <div class="card-head"><h2>Routing rules</h2><span class="small muted"><?= count($rules) ?> rule(s) configured</span><span class="grow"></span><a class="btn btn-sm btn-primary" href="<?= e(url('/admin/notifications?rule=new')) ?>"><?= icon('plus', '', 14) ?> Add rule</a></div>
    <p class="small muted">Route alerts to specific people when something happens: new sign-ups by category, issue reports, completed profiles, training completions and story submissions.</p>
    <?php if (!$rules): ?><p class="empty">No rules yet. Add your first notification rule to start routing alerts.</p><?php endif; ?>
    <?php foreach ($rules as $r): [$label, $ico] = $triggers[$r['trigger_type']] ?? ['Custom rule', 'settings']; ?>
    <div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line)">
      <span class="badge-ico"><?= icon($ico, '', 18) ?></span>
      <div style="flex:1"><strong><?= e($r['rule_name']) ?></strong> <span class="chip"><?= e($label) ?></span><?= !(int) $r['is_active'] ? ' <span class="badge badge-inactive">Inactive</span>' : '' ?><?= $r['priority'] === 'high' ? ' <span class="badge badge-rejected">High</span>' : '' ?>
        <div class="small muted">Notify: <?= e($r['recipient_name'] ?: '') ?> (<?= e($r['recipient_email']) ?>) · <?= e(implode(', ', array_map(fn($c) => $channels[$c] ?? $c, $r['channels']))) ?></div></div>
      <div class="actions">
        <form method="post" action="<?= e(url('/admin/notifications/rules')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-sm btn-ghost"><?= (int) $r['is_active'] ? 'Pause' : 'Activate' ?></button></form>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/notifications?rule=' . $r['id'])) ?>">Edit</a>
        <form method="post" action="<?= e(url('/admin/notifications/rules')) ?>" data-confirm="Delete this rule?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($edit !== null): $r = $edit; ?>
  <div class="card">
    <h2><?= $r ? 'Edit rule' : 'Add notification rule' ?></h2>
    <form method="post" action="<?= e(url('/admin/notifications/rules')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
      <div class="field"><label>Rule name</label><input name="rule_name" value="<?= (old('rule_name', $r['rule_name'] ?? '')) ?>" placeholder="e.g. Global Volunteer Lead Alert" required></div>
      <div class="field"><label>Trigger</label><select name="trigger_type"><?php foreach ($triggers as $k => [$l]): if ($k === 'custom') { continue; } ?><option value="<?= e($k) ?>" <?= ($r['trigger_type'] ?? 'category_global') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="grid-2">
        <div class="field"><label>Recipient name</label><input name="recipient_name" value="<?= e($r['recipient_name'] ?? '') ?>"></div>
        <div class="field"><label>Recipient email</label><input type="email" name="recipient_email" value="<?= (old('recipient_email', $r['recipient_email'] ?? '')) ?>" required></div>
        <div class="field"><label>WhatsApp number (optional)</label><input name="recipient_whatsapp" value="<?= e($r['recipient_whatsapp'] ?? '') ?>" placeholder="+44 7700 000000"></div>
        <div class="field"><label>Priority</label><select name="priority"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $k => $l): ?><option value="<?= $k ?>" <?= ($r['priority'] ?? 'normal') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Notification channels</label><div class="choices"><?php foreach ($channels as $k => $l): ?><label class="check"><input type="checkbox" name="channels[]" value="<?= e($k) ?>" <?= in_array($k, $r['channels'] ?? ['email'], true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div><div class="field-hint">In-app and dashboard alerts need the recipient to have an account here.</div></div>
      <div class="field"><label>Email subject template</label><input name="email_subject_template" value="<?= e($r['email_subject_template'] ?? NotificationRules::DEFAULT_SUBJECT) ?>"><div class="field-hint">Variables: {{full_name}} {{email}} {{category}} {{country}} {{title}}</div></div>
      <div class="field"><label>Email body template (HTML, leave blank for the default card)</label><textarea name="email_body_template" rows="6" style="font-family:monospace;font-size:.85rem"><?= e($r['email_body_template'] ?? '') ?></textarea><div class="field-hint">Available: {{full_name}} {{email}} {{phone}} {{category}} {{country}} {{city}} {{university}} {{skills}} {{profile_completion}} {{admin_review_link}} {{title}} {{description}}; wrap optional blocks in {{#university}}…{{/university}}.</div></div>
      <label class="check" style="margin-bottom:12px"><input type="checkbox" name="is_active" value="1" <?= (int) ($r['is_active'] ?? 1) ? 'checked' : '' ?>> Rule is active</label>
      <div class="actions"><button class="btn btn-primary">Save rule</button><a class="btn btn-ghost" href="<?= e(url('/admin/notifications')) ?>">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'log'): ?>
<div class="card">
  <div class="card-head"><h2>Activity log</h2><span class="grow"></span><div class="actions"><?php foreach (['' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Pending'] as $k => $l): ?><a class="pill <?= $status === $k ? 'pill-accent' : '' ?>" href="<?= e(url('/admin/notifications?tab=log' . ($k ? '&status=' . $k : ''))) ?>"><?= $l ?></a><?php endforeach; ?></div></div>
  <?php if (!$logs): ?><p class="empty">No notification logs<?= $status ? ' with status "' . e($status) . '"' : ' yet' ?>.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Volunteer</th><th>Recipient</th><th>Trigger</th><th>Channel</th><th>Status</th><th>Time</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?>
    <tr><td><?= e($l['volunteer_name'] ?: '—') ?><br><span class="small muted"><?= e($l['volunteer_email']) ?><?= $l['volunteer_category'] ? ' · ' . e($l['volunteer_category']) : '' ?></span></td><td><?= e($l['recipient_name'] ?: '') ?><br><span class="small muted"><?= e($l['recipient_email']) ?></span></td><td class="small"><?= e(str_replace('_', ' ', $l['trigger_type'])) ?><br><span class="muted"><?= e($l['rule_name']) ?></span></td><td><span class="chip"><?= e($channels[$l['channel']] ?? $l['channel']) ?></span></td><td><span class="badge badge-<?= ['sent' => 'ok', 'failed' => 'failed', 'pending' => 'pending'][$l['status']] ?? 'default' ?>"><?= e($l['status']) ?></span><?= $l['error_message'] ? '<br><span class="small muted">' . e($l['error_message']) . '</span>' : '' ?></td><td class="small"><?= e(fmt_datetime($l['sent_at'] ?: $l['created_at'])) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <div class="card-head"><h2>My notifications</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/notifications')) ?>">Open full list</a></div>
  <?php if (!$mine): ?><p class="empty">Nothing yet.</p><?php else: ?><ul class="activity"><?php foreach ($mine as $n): ?><li><strong><?= e($n['title']) ?></strong><?= $n['message'] ? ' — ' . e($n['message']) : '' ?> <span class="muted small"><?= e(fmt_datetime($n['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
