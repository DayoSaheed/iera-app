<?php $title = \App\Models\User::fullName($m); $active = 'mentees'; $breadcrumbs = ['Mentees' => '/mentees', $title => null]; ?>
<div class="grid-2">
  <div class="card-stack">
    <div class="card"><h2>Profile</h2>
      <dl class="kv"><dt>Email</dt><dd><?= e($m['email']) ?></dd><dt>Phone</dt><dd><?= e(\App\Models\User::phoneOf($m)) ?></dd><dt>Status</dt><dd><span class="badge badge-<?= e($m['status']) ?>"><?= e(\App\Models\User::statusLabel($m)) ?></span></dd>
      <?php foreach ($fields as $f): if (empty($f['name']) || in_array($f['name'], ['first_name', 'last_name', 'phone', 'contact_consent'], true) || in_array($f['type'] ?? '', ['section', 'text_copy', 'typography'], true)) continue; $v = $values[$f['name']] ?? null; if ($v === null || $v === '' || $v === []) continue; ?><dt><?= e($f['label']) ?></dt><dd><?= e(\App\Services\FormRenderer::displayValue($f, $v)) ?></dd><?php endforeach; ?></dl>
      <?php if ($assignment && $assignment['accepted_at']): ?><p class="small muted">You accepted this mentee on <?= e(fmt_date($assignment['accepted_at'])) ?>.</p><?php endif; ?>
    </div>
    <div class="card"><h2>Mentors</h2><?php if (!$assigned): ?><p class="muted">None assigned.</p><?php else: ?><ul class="check-list"><?php foreach ($assigned as $a): ?><li><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?> · <?= e($a['email']) ?><span class="badge badge-<?= $a['accepted_at'] ? 'approved' : ($a['rejected_at'] ? 'rejected' : 'pending') ?>"><?= $a['accepted_at'] ? 'accepted' : ($a['rejected_at'] ? 'declined' : 'pending') ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
  </div>
  <div class="card-stack">
    <div class="card"><h2>Comments</h2>
      <form method="post" action="<?= e(url('/users/' . $m['id'] . '/comments')) ?>" class="stack"><?= csrf_field() ?><div class="field"><label>Add a note for the coordinator and other mentors</label><textarea name="comment" rows="2" required></textarea></div><button class="btn btn-sm btn-primary">Add comment</button></form>
      <?php if ($comments): ?><ul class="check-list" style="margin-top:12px"><?php foreach ($comments as $c): ?><li><div><?= nl2br(e($c['comment'])) ?><br><small class="muted"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['author_email'] ?? 'System')) ?> · <?= e(fmt_datetime($c['created_at'])) ?></small></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card"><h2>Submitted mentor reports (<?= count($reports) ?>)</h2>
      <?php if (!$reports): ?><p class="muted">None yet.</p><?php else: ?><table class="table"><thead><tr><th>Week</th><th>Mentor</th><th>Mentored</th><th>Lesson</th><th></th></tr></thead><tbody><?php foreach ($reports as $p): ?><tr><td><?= e($p['period_key']) ?></td><td><?= e($p['assignee_name']) ?></td><td><?= e(ucfirst((string) ($p['submit']['did_you'] ?? ''))) ?></td><td><?= e(\App\Models\FormTemplate::optionLabel('mentor', 'which_lesson', $p['submit']['which_lesson'] ?? '')) ?></td><td><a class="btn btn-sm btn-ghost" href="<?= e(url('/reports/' . $p['id'])) ?>">View</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    </div>
  </div>
</div>
