<?php
use App\Services\Communications;
$title = 'Communications'; $active = 'cc-comms';
$catChip = fn(string $c) => '<span class="chip">' . e($c) . '</span>';
$base = '/admin/communications';
?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach (['templates' => 'Templates', 'send' => 'Send', 'scheduled' => 'Scheduled', 'log' => 'Log'] as $k => $l): ?><a class="tab <?= $tab === $k ? 'is-active' : '' ?>" href="<?= e(url($base . '?tab=' . $k)) ?>"><?= $l ?></a><?php endforeach; ?></div>
  <span class="grow"></span><span class="small muted">Transactional emails (sign-in codes, application received/approved) live under System admin → Email templates.</span>
</div>

<?php if ($tab === 'templates'): ?>
<div class="<?= ($edit !== null || $preview) ? 'two-col' : '' ?>">
  <div class="card">
    <div class="card-head"><h2>Email templates</h2><span class="small muted"><?= count($templates) ?> template(s)</span><span class="grow"></span><a class="btn btn-sm btn-primary" href="<?= e(url($base . '?template=new')) ?>"><?= icon('plus', '', 14) ?> New template</a></div>
    <?php if (!$templates): ?><p class="empty">No templates yet. Create your first email template to get started.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Category</th><th>Status</th><th>Subject</th><th></th></tr></thead><tbody>
    <?php foreach ($templates as $t): ?>
      <tr><td><strong><?= e($t['internal_name']) ?></strong><br><span class="small muted">Last edited by <?= e($t['last_edited_by_name'] ?: '—') ?> · <?= e(fmt_date($t['updated_at'])) ?></span></td><td><?= $catChip($t['category']) ?></td><td><span class="badge badge-<?= $t['status'] === 'active' ? 'active' : 'default' ?>"><?= e($t['status']) ?></span></td><td class="small"><?= e($t['subject_line']) ?></td>
        <td><div class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url($base . '?preview=' . $t['id'])) ?>" title="Preview"><?= icon('eye', '', 14) ?></a><a class="btn btn-sm btn-ghost" href="<?= e(url($base . '?template=' . $t['id'])) ?>">Edit</a>
          <form method="post" action="<?= e(url($base . '/templates')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="duplicate"><button class="btn btn-sm btn-ghost" title="Duplicate"><?= icon('clipboard', '', 14) ?></button></form>
          <form method="post" action="<?= e(url($base . '/templates')) ?>" data-confirm="<?= isset($used[$t['id']]) ? 'This template has been used in previous sends. Deleting it will not remove historical logs. Delete anyway?' : 'Delete this template?' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-ghost" title="Delete"><?= icon('trash', '', 14) ?></button></form></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
  <?php if ($preview): ?>
  <div class="card">
    <div class="card-head"><h2>Template preview</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url($base)) ?>">Close</a></div>
    <p class="small muted">Showing example placeholder values. No email will be sent.</p>
    <p><strong>Subject:</strong> <?= e(Communications::render($preview['subject_line'], $sample)) ?></p>
    <iframe sandbox="" srcdoc="<?= e(Communications::render($preview['body_html'], $sample)) ?>" style="width:100%;height:420px;border:1px solid var(--line);border-radius:10px;background:#fff"></iframe>
  </div>
  <?php elseif ($edit !== null): ?>
  <div class="card">
    <h2><?= $edit ? 'Edit template' : 'New template' ?></h2>
    <form method="post" action="<?= e(url($base . '/templates')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="field"><label>Internal name</label><input name="internal_name" value="<?= (old('internal_name', $edit['internal_name'] ?? '')) ?>" placeholder="e.g. Event Reminder – June 2026" required></div>
      <div class="grid-2">
        <div class="field"><label>Category</label><select name="category"><?php foreach ($categories as $c): ?><option <?= ($edit['category'] ?? 'general') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Status</label><select name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="active" <?= ($edit['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option></select></div>
      </div>
      <div class="field"><label>Subject line</label><input name="subject_line" value="<?= (old('subject_line', $edit['subject_line'] ?? '')) ?>" placeholder="e.g. Don't forget — {{event_name}} is tomorrow!" required><div class="field-hint">Placeholders: <?= e(implode(' ', array_map(fn($p) => '{{' . $p . '}}', Communications::PLACEHOLDERS))) ?></div></div>
      <div class="field"><label>Email body (HTML)</label><textarea name="body_html" rows="14" style="font-family:monospace;font-size:.85rem" placeholder="Paste exported HTML here" required><?= (old('body_html', $edit['body_html'] ?? '')) ?></textarea></div>
      <div class="field"><label>Plain text summary (used as the in-app notification)</label><textarea name="plain_text_summary" rows="2" placeholder="Optional short summary"><?= (old('plain_text_summary', $edit['plain_text_summary'] ?? '')) ?></textarea></div>
      <div class="actions"><button class="btn btn-primary">Save template</button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url($base . '?preview=' . $edit['id'])) ?>">Preview</a><?php endif; ?><a class="btn btn-ghost" href="<?= e(url($base)) ?>">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'send'): $f = $filters; ?>
<form method="post" action="<?= e(url($base . '/send')) ?>" id="sendForm"><?= csrf_field() ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><span class="section-num">1</span><h2>Select template</h2></div>
      <?php if (!$templates): ?><p class="empty">No templates yet. <a href="<?= e(url($base . '?template=new')) ?>">Create one</a>.</p><?php endif; ?>
      <?php $tplLink = fn(int $id) => url($base . '?' . http_build_query(array_filter(['tab' => 'send', 'template_id' => $id, 'apply' => $applied ? 1 : null] + $filters, fn($v) => $v !== '' && $v !== null))); ?>
      <ul class="check-list"><?php foreach ($templates as $t): ?><li><a href="<?= e($tplLink((int) $t['id'])) ?>" style="display:flex;gap:10px;align-items:center;text-decoration:none;color:inherit<?= $template && $template['id'] === $t['id'] ? ';font-weight:700' : '' ?>"><?= icon($template && $template['id'] === $t['id'] ? 'check-circle' : 'circle', '', 16) ?><span style="flex:1"><?= e($t['internal_name']) ?><br><span class="small muted"><?= e($t['subject_line']) ?></span></span><?= $catChip($t['category']) ?><?= $t['status'] !== 'active' ? '<span class="badge badge-default">Draft</span>' : '' ?></a></li><?php endforeach; ?></ul>
      <?php if ($template): ?><input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
        <?php if ($template['status'] !== 'active'): ?><div class="flash flash-warning" style="margin:10px 0 0">This template is a draft. Activate it in the Templates tab before sending.</div><?php endif; ?>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><span class="section-num">2</span><h2>Build the recipient list</h2></div>
      <div class="help-box small" style="margin-bottom:12px">Bulk filter volunteers, then add individuals. Opt-out preferences are checked at send time per volunteer, so the delivered count may be lower.</div>
      <div class="grid-3">
        <div class="field"><label>Application status</label><select name="f_status" form="filterForm"><option value="">Any</option><?php foreach (['Approved', 'Under Review', 'New', 'Waitlisted'] as $s): ?><option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Category</label><select name="f_category" form="filterForm"><option value="">Any</option><?php foreach (['Global', 'UK', 'Online'] as $s): ?><option <?= $f['volunteer_category'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Country</label><input name="f_country" form="filterForm" value="<?= e($f['country']) ?>" placeholder="e.g. United Kingdom"></div>
        <div class="field"><label>Inactive for N days</label><input type="number" name="f_inactive_days" form="filterForm" value="<?= e($f['inactive_days']) ?>" placeholder="e.g. 30"></div>
        <div class="field"><label>Max hours this month (≤)</label><input type="number" step="0.5" name="f_max_hours" form="filterForm" value="<?= e($f['max_hours_this_month']) ?>" placeholder="e.g. 0"></div>
        <div class="field"><label>&nbsp;</label><button class="btn btn-sm btn-dark" form="filterForm">Apply filters</button></div>
      </div>
      <?php foreach (['status' => 'f_status', 'volunteer_category' => 'f_category', 'country' => 'f_country', 'inactive_days' => 'f_inactive_days', 'max_hours_this_month' => 'f_max_hours'] as $k => $n): ?><input type="hidden" name="<?= $n ?>" value="<?= e($f[$k]) ?>"><?php endforeach; ?>
      <?php if ($applied): ?>
        <p class="small"><strong><?= count($matched) ?> matched</strong> (of <?= $total ?> applications). Untick anyone to exclude them.</p>
        <div style="max-height:280px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:8px 12px">
          <?php foreach ($matched as $a): ?><label class="check small" style="padding:3px 0"><input type="checkbox" name="emails[]" value="<?= e($a['email']) ?>" checked> <?= e($a['name']) ?> <span class="muted"><?= e($a['email']) ?> · <?= e($a['volunteer_category']) ?> · <?= e($a['status']) ?></span></label><?php endforeach; ?>
          <?php if (!$matched): ?><p class="muted small">Nobody matches those filters.</p><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="field" style="margin-top:12px"><label>Add individuals (emails, comma or line separated)</label><textarea name="manual_emails" rows="2" placeholder="name@example.org, other@example.org" list="people"></textarea>
        <datalist id="people"><?php foreach (array_slice($people, 0, 500) as $p): ?><option value="<?= e($p['email']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></datalist></div>
    </div>
  </div>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><span class="section-num">3</span><h2>Send type</h2></div>
      <label class="check" style="margin-bottom:8px"><input type="radio" name="send_type" value="now" checked> <span><strong>Send now</strong><br><span class="small muted">Fires immediately to all recipients</span></span></label>
      <label class="check"><input type="radio" name="send_type" value="scheduled"> <span><strong>Schedule</strong><br><span class="small muted">Set a specific date and time</span></span></label>
      <div class="field" style="margin-top:10px"><label>Scheduled date &amp; time</label><input type="datetime-local" name="scheduled_at"></div>
    </div>
    <div class="card">
      <div class="card-head"><span class="section-num">4</span><h2>Review &amp; confirm</h2></div>
      <dl class="kv"><dt>Template</dt><dd><?= $template ? e($template['internal_name']) : '<span class="muted">none selected</span>' ?></dd><dt>Category</dt><dd><?= $template ? e($template['category']) : '—' ?></dd><dt>Recipients</dt><dd><?= $applied ? count($matched) . ' from filter + any individuals added' : 'Apply filters or add individuals' ?></dd></dl>
      <?php if ($template): ?><p class="small"><a href="<?= e(url($base . '?preview=' . $template['id'])) ?>" target="_blank">Preview this template</a></p><?php endif; ?>
      <label class="check" style="margin:12px 0"><input type="checkbox" name="confirm" value="1"> Yes, I've reviewed the recipients and am ready to send</label>
      <button class="btn btn-primary" <?= !$template || $template['status'] !== 'active' ? 'disabled' : '' ?>><?= icon('send', '', 14) ?> Send / schedule</button>
    </div>
  </div>
</div>
</form>
<form method="get" action="<?= e(url($base)) ?>" id="filterForm"><input type="hidden" name="tab" value="send"><input type="hidden" name="apply" value="1"><?php if ($template): ?><input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>"><?php endif; ?>
  <?php foreach (['status', 'volunteer_category', 'country', 'inactive_days', 'max_hours_this_month'] as $k): ?><input type="hidden" name="<?= $k ?>" value="" data-sync="<?= $k ?>"><?php endforeach; ?></form>
<script>
(function(){var ff=document.getElementById('filterForm');var map={f_status:'status',f_category:'volunteer_category',f_country:'country',f_inactive_days:'inactive_days',f_max_hours:'max_hours_this_month'};ff.addEventListener('submit',function(){Object.keys(map).forEach(function(n){var el=document.querySelector('[name="'+n+'"][form="filterForm"]');var h=ff.querySelector('[data-sync="'+map[n]+'"]');if(el&&h){h.value=el.value;}});});})();
</script>

<?php elseif ($tab === 'scheduled'): ?>
<div class="card">
  <div class="card-head"><h2>Scheduled sends</h2><span class="small muted"><?= count($pending) ?> pending</span><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url($base . '?tab=scheduled')) ?>"><?= icon('refresh', '', 14) ?> Refresh</a></div>
  <?php if (!$pending): ?><p class="empty">No scheduled sends. Upcoming scheduled sends will appear here.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Template</th><th>Category</th><th>Recipients</th><th>Scheduled</th><th>Created by</th><th></th></tr></thead><tbody>
  <?php foreach ($pending as $s): ?>
    <tr><td><strong><?= e($s['template_name']) ?></strong><br><span class="small muted"><?= e($s['send_type']) ?></span></td><td><?= $catChip((string) $s['category']) ?></td><td><?= (int) $s['recipient_count'] ?></td><td><?= e(fmt_datetime($s['scheduled_at']) ?: 'as soon as possible') ?></td><td class="small"><?= e($s['created_by_name'] ?: $s['created_by_email']) ?></td>
      <td><details><summary class="btn btn-sm btn-ghost" style="list-style:none">Manage</summary>
        <div style="margin-top:8px;display:grid;gap:8px">
          <form method="post" action="<?= e(url($base . '/sends/' . $s['id'])) ?>" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="reschedule"><input type="datetime-local" name="scheduled_at" class="input" style="max-width:220px"><button class="btn btn-sm">Reschedule</button></form>
          <form method="post" action="<?= e(url($base . '/sends/' . $s['id'])) ?>" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input class="input" name="reason" placeholder="Reason for cancellation (required)" style="max-width:260px" required><button class="btn btn-sm btn-danger">Cancel send</button></form>
          <form method="post" action="<?= e(url($base . '/sends/' . $s['id'])) ?>" data-confirm="Send this now to <?= (int) $s['recipient_count'] ?> volunteer(s)?"><?= csrf_field() ?><input type="hidden" name="action" value="send_now"><button class="btn btn-sm btn-primary">Send now</button></form>
        </div></details></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
<div class="card">
  <div class="card-head"><h2>Recent sends</h2></div>
  <?php if (!$recent): ?><p class="empty">Nothing sent yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Template</th><th>Type</th><th>Recipients</th><th>Status</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($recent as $s): $res = $s['result_json'] ? json_decode($s['result_json'], true) : []; ?>
    <tr><td><?= e(fmt_datetime($s['sent_at'] ?: $s['created_at'])) ?></td><td><?= e($s['template_name']) ?></td><td><?= e($s['send_type']) ?></td><td><?= (int) $s['recipient_count'] ?></td><td><span class="badge badge-<?= ['sent' => 'ok', 'failed' => 'failed', 'cancelled' => 'inactive'][$s['status']] ?? 'default' ?>"><?= e($s['status']) ?></span></td><td class="small"><?= $res ? (int) ($res['sent'] ?? 0) . ' sent · ' . (int) ($res['failed'] ?? 0) . ' failed · ' . (int) ($res['skipped'] ?? 0) . ' skipped' : e($s['cancellation_reason'] ?: '—') ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<?php else: $lf = $logFilters; ?>
<div class="card">
  <div class="card-head"><h2>Communication log</h2><span class="small muted"><?= count($logs) ?> entr<?= count($logs) === 1 ? 'y' : 'ies' ?></span></div>
  <form class="table-toolbar" method="get" action="<?= e(url($base)) ?>"><input type="hidden" name="tab" value="log">
    <input class="input" name="q" value="<?= e($lf['q']) ?>" placeholder="Volunteer name or email"><input class="input" name="template" value="<?= e($lf['template']) ?>" placeholder="Template name" style="max-width:180px">
    <select name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option <?= $lf['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <select name="trigger"><option value="">All triggers</option><?php foreach (['manual', 'scheduled', 'automatic'] as $c): ?><option <?= $lf['trigger'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option><?php endforeach; ?></select>
    <select name="delivery"><option value="">Email: all</option><option value="sent" <?= $lf['delivery'] === 'sent' ? 'selected' : '' ?>>Sent</option><option value="failed" <?= $lf['delivery'] === 'failed' ? 'selected' : '' ?>>Failed</option></select>
    <select name="read"><option value="">In-app: all</option><option value="read" <?= $lf['read'] === 'read' ? 'selected' : '' ?>>Read</option><option value="unread" <?= $lf['read'] === 'unread' ? 'selected' : '' ?>>Unread</option></select>
    <button class="btn btn-sm btn-dark">Filter</button>
  </form>
  <?php if (!$logs): ?><p class="empty">No log entries found.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Volunteer</th><th>Template</th><th>Category</th><th>Sent</th><th>Trigger</th><th>Email</th><th>In-app</th><th></th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?>
    <tr><td><strong><?= e($l['volunteer_name']) ?></strong><br><span class="small muted"><?= e($l['volunteer_email']) ?></span></td><td class="small"><?= e($l['template_name']) ?></td><td><?= $catChip((string) $l['category']) ?></td><td class="small"><?= e(fmt_datetime($l['sent_at'])) ?></td><td><span class="chip"><?= e($l['trigger_type']) ?></span></td><td><span class="badge badge-<?= $l['delivery_status'] === 'sent' ? 'ok' : 'failed' ?>"><?= e($l['delivery_status']) ?></span></td><td class="small"><?= (int) $l['in_app_read'] ? 'Read ' . e(fmt_datetime($l['read_at'])) : ($l['notification_id'] ? 'Unread' : '—') ?></td><td><a class="btn btn-sm btn-ghost" href="<?= e(url($base . '/log/' . $l['id'])) ?>" target="_blank" title="View email"><?= icon('eye', '', 14) ?></a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
