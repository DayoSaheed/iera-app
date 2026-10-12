<?php use App\Models\Community; $title = 'Feedback & Issues'; $active = 'feedback'; ?>
<p class="muted" style="margin-top:-6px">Track your reports and vote on feature ideas. Use the <strong>Report Issue</strong> button at the bottom right of any page to send new feedback.</p>
<div class="tabs"><a class="tab <?= $tab === 'mine' ? 'is-active' : '' ?>" href="<?= e(url('/my-feedback')) ?>">My Reports (<?= count($mine) ?>)</a><a class="tab <?= $tab === 'ideas' ? 'is-active' : '' ?>" href="<?= e(url('/my-feedback?tab=ideas')) ?>">Feature Ideas</a></div>
<?php if ($tab === 'mine'): ?>
  <?php if (!$mine): ?><div class="card"><p class="empty">No reports yet. Use the "Report Issue" button to submit feedback.</p></div><?php endif; ?>
  <div class="card-stack"><?php foreach ($mine as $f): ?>
  <div class="card">
    <div class="actions"><span class="badge badge-<?= ['New' => 'new', 'Investigating' => 'pending', 'In Progress' => 'running', 'Fixed' => 'ok', 'Closed' => 'default', 'Need More Info' => 'pending'][$f['status']] ?? 'default' ?>"><?= e($f['status']) ?></span><span class="chip"><?= icon(Community::ISSUE_TYPES[$f['issue_type']] ?? 'file-text', '', 11) ?> <?= e($f['issue_type']) ?></span><span class="chip"><?= e($f['priority']) ?></span><span class="grow"></span><small class="muted"><?= e(fmt_date($f['created_at'])) ?></small></div>
    <h3 style="margin:8px 0 4px"><?= e($f['title']) ?></h3><p class="small"><?= nl2br(e($f['description'])) ?></p>
    <p class="small muted"><?= e(Community::FEEDBACK_MESSAGES[$f['status']] ?? '') ?></p>
    <details><summary class="small"><?= count($f['comments']) ?> comment<?= count($f['comments']) === 1 ? '' : 's' ?> · add one</summary>
      <?php foreach ($f['comments'] as $c): ?><div class="comment <?= $c['author_role'] === 'admin' ? 'is-admin' : '' ?>"><strong><?= e($c['author_name']) ?></strong> <?= $c['author_role'] === 'admin' ? '<span class="badge badge-pending">Admin</span>' : '' ?> <small class="muted"><?= e(fmt_datetime($c['posted_at'])) ?></small><br><?= nl2br(e($c['text'])) ?></div><?php endforeach; ?>
      <form method="post" action="<?= e(url('/feedback/' . $f['id'])) ?>" class="actions" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="comment"><input class="input" name="text" placeholder="Write a comment..." required style="flex:1"><button class="btn btn-sm btn-dark"><?= icon('send', '', 13) ?></button></form>
    </details>
  </div><?php endforeach; ?></div>
<?php else: ?>
  <div class="help-box" style="margin-bottom:14px"><strong>Feature Ideas</strong> — Vote on suggestions from the community. The most popular ones get prioritised!</div>
  <?php if (!$ideas): ?><div class="card"><p class="empty">No suggestions yet. Be the first!</p></div><?php endif; ?>
  <div class="card-stack"><?php foreach ($ideas as $f): $voted = in_array($u['id'], $f['votes'], true); ?>
  <div class="card" style="display:flex;gap:14px;align-items:flex-start"><form method="post" action="<?= e(url('/feedback/' . $f['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="vote"><button class="btn btn-sm <?= $voted ? 'btn-primary' : 'btn-ghost' ?>"><?= icon('thumbs-up', '', 14) ?> <?= count($f['votes']) ?></button></form><div class="grow"><span class="badge badge-default"><?= e($f['status']) ?></span><h3 style="margin:6px 0 4px"><?= e($f['title']) ?></h3><p class="small"><?= nl2br(e($f['description'])) ?></p><small class="muted"><?= e($f['submitted_by_name'] ?: 'Anonymous') ?> · <?= e(fmt_date($f['created_at'])) ?></small></div></div>
  <?php endforeach; ?></div>
<?php endif; ?>
