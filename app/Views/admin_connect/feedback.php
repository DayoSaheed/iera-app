<?php use App\Models\Community; $title = 'Issue tracker'; $active = 'cc-feedback';
$sb = ['New' => 'new', 'Investigating' => 'pending', 'In Progress' => 'running', 'Fixed' => 'ok', 'Closed' => 'default', 'Need More Info' => 'pending'];
$pb = ['High' => 'rejected', 'Medium' => 'pending', 'Low' => 'ok'];
$qs = fn(array $extra) => '/admin/feedback?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'type' => $type, 'priority' => $priority] + $extra, fn($v) => $v !== '' && $v !== null));
?>
<div class="actions" style="margin-bottom:12px"><?php foreach (['New', 'In Progress', 'Fixed'] as $s): ?><a class="pill <?= $status === $s ? 'pill-accent' : '' ?>" href="<?= e(url($qs(['status' => $status === $s ? '' : $s]))) ?>"><?= $counts[$s] ?> <?= $s ?></a><?php endforeach; ?></div>
<div class="<?= $open ? 'two-col' : '' ?>">
  <div class="card">
    <form class="table-toolbar" method="get" action="<?= e(url('/admin/feedback')) ?>">
      <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search title, submitter, page…">
      <select name="status"><option value="">All statuses</option><?php foreach (Community::FEEDBACK_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
      <select name="type"><option value="">All types</option><?php foreach (array_keys(Community::ISSUE_TYPES) as $t): ?><option <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
      <select name="priority"><option value="">All priorities</option><?php foreach (['Low', 'Medium', 'High'] as $p): ?><option <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-dark">Filter</button><span class="small muted"><?= count($rows) ?> issues</span>
    </form>
    <?php if (!$rows): ?><p class="empty">No issues match your filters.</p><?php endif; ?>
    <?php foreach ($rows as $f): ?>
    <a href="<?= e(url($qs(['open' => $f['id']]))) ?>" style="display:block;padding:10px 0;border-bottom:1px solid var(--line);text-decoration:none;color:inherit<?= $open && $open['id'] === $f['id'] ? ';background:var(--accent-50)' : '' ?>">
      <span class="badge badge-<?= e($sb[$f['status']] ?? 'default') ?>"><?= e($f['status']) ?></span> <span class="badge badge-<?= e($pb[$f['priority']] ?? 'default') ?>"><?= e($f['priority']) ?></span> <span class="chip"><?= e($f['issue_type']) ?></span>
      <div><strong><?= e($f['title']) ?></strong></div>
      <div class="small muted"><?= e(mb_strimwidth((string) $f['description'], 0, 110, '…')) ?></div>
      <div class="small muted"><?= e($f['device_type'] ?: '') ?> · <?= (int) $f['is_anonymous'] ? 'Anonymous' : e($f['submitted_by_name']) ?> · <?= e(fmt_date($f['created_at'])) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if ($open): $f = $open; $act = url('/admin/feedback/' . $f['id']); ?>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Issue detail</h2><span class="grow"></span>
        <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $f['status'] === 'Closed' ? 'In Progress' : 'Closed' ?>"><button class="btn btn-sm"><?= $f['status'] === 'Closed' ? 'Reopen issue' : 'Close issue' ?></button></form>
        <form method="post" action="<?= $act ?>" data-confirm="Delete this issue permanently? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form>
      </div>
      <h3 style="margin:0 0 6px"><?= e($f['title']) ?></h3>
      <p class="small"><?= nl2br(e($f['description'])) ?></p>
      <dl class="kv"><dt>Type</dt><dd><?= e($f['issue_type']) ?></dd><dt>Device</dt><dd><?= e(implode(' · ', array_filter([$f['device_type'], $f['browser'], $f['screen_size']])) ?: '—') ?></dd><dt>Page</dt><dd><?= e($f['page_affected'] ?: '—') ?><?= $f['page_url'] ? ' · <a href="' . e($f['page_url']) . '" target="_blank">View page</a>' : '' ?></dd><dt>By</dt><dd><?= (int) $f['is_anonymous'] ? 'Anonymous' : e($f['submitted_by_name']) . ($submitter ? ' (' . e($submitter['email']) . ')' : '') ?></dd><dt>Reported</dt><dd><?= e(fmt_datetime($f['created_at'])) ?></dd><dt>Votes</dt><dd><?= count($f['votes']) ?></dd></dl>
      <?php if ($screenshot): ?><p style="margin-top:10px"><a href="<?= e(url('/documents/' . $screenshot['id'])) ?>" target="_blank"><img src="<?= e(url('/documents/' . $screenshot['id'])) ?>" alt="Screenshot" style="max-width:100%;border:1px solid var(--line);border-radius:8px"></a></p><?php endif; ?>
    </div>
    <div class="card">
      <div class="field"><label>Update status</label><div class="actions"><?php foreach (Community::FEEDBACK_STATUSES as $s): ?><form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($s) ?>"><button class="btn btn-sm <?= $f['status'] === $s ? 'btn-dark' : 'btn-ghost' ?>"><?= e($s) ?></button></form><?php endforeach; ?></div><div class="field-hint">The volunteer is notified in-app when the status changes.</div></div>
      <div class="field"><label>Priority</label><div class="actions"><?php foreach (['Low', 'Medium', 'High'] as $p): ?><form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="priority"><input type="hidden" name="priority" value="<?= $p ?>"><button class="btn btn-sm <?= $f['priority'] === $p ? 'btn-dark' : 'btn-ghost' ?>"><?= $p ?></button></form><?php endforeach; ?></div></div>
      <form method="post" action="<?= $act ?>" class="field"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><label>Assign to</label><div class="actions"><input class="input" name="assigned_to" value="<?= e($f['assigned_to']) ?>" placeholder="Enter email or name"><button class="btn btn-sm">Save</button></div></form>
      <form method="post" action="<?= $act ?>" class="field"><?= csrf_field() ?><input type="hidden" name="action" value="note"><label>Internal notes (admin only)</label><textarea name="admin_notes" rows="3" placeholder="Add an internal note…"><?= e($f['admin_notes']) ?></textarea><button class="btn btn-sm" style="margin-top:6px">Save note</button></form>
    </div>
    <div class="card">
      <div class="card-head"><h2>Conversation with the reporter</h2></div>
      <?php if (!$f['comments']): ?><p class="small muted">No replies yet.</p><?php endif; ?>
      <?php foreach ($f['comments'] as $c): ?><div class="comment <?= ($c['author_role'] ?? '') === 'admin' ? 'is-admin' : '' ?>"><strong><?= e($c['author_name']) ?></strong> <?= ($c['author_role'] ?? '') === 'admin' ? '<span class="chip">admin</span>' : '' ?> <span class="small muted"><?= e(fmt_datetime($c['posted_at'] ?? null)) ?></span><br><?= nl2br(e($c['text'])) ?></div><?php endforeach; ?>
      <form method="post" action="<?= $act ?>" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="comment"><div class="field"><textarea name="text" rows="2" placeholder="Reply to the reporter (visible to them)…" required></textarea></div><button class="btn btn-sm btn-primary">Post reply</button></form>
    </div>
  </div>
  <?php endif; ?>
</div>
