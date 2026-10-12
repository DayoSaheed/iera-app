<?php $title = 'Groups'; $active = 'cc-groups'; ?>
<div class="card">
  <div class="card-head"><h2>Groups management</h2><span class="small muted"><?= count($groups) ?> groups · <?= $totalMembers ?> memberships</span><span class="grow"></span><form class="table-toolbar" style="margin:0" method="get" action="<?= e(url('/admin/groups')) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search groups…"><button class="btn btn-sm btn-dark">Search</button></form></div>
  <p class="small muted">Volunteers join groups themselves from the Groups page. Remove a member here if needed.</p>
  <?php if (!$groups): ?><p class="empty">No groups yet.</p><?php endif; ?>
  <div class="grid-3">
    <?php foreach ($groups as $g): ?>
    <div style="border:1px solid var(--line);border-radius:12px;padding:14px">
      <strong><?= e($g['name']) ?></strong> <code class="small muted"><?= e($g['slug']) ?></code><div class="small muted" style="margin:4px 0 8px"><?= count($g['members']) ?> members</div>
      <?php foreach ($g['members'] as $m): ?><div style="display:flex;gap:6px;align-items:center;font-size:.85rem;padding:3px 0"><span style="flex:1"><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['email']) ?> <span class="muted"><?= e($m['email']) ?></span></span><form method="post" action="<?= e(url('/admin/groups/remove')) ?>" data-confirm="Remove this member from the group?"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($m['user_id']) ?>"><input type="hidden" name="slug" value="<?= e($g['slug']) ?>"><button class="btn btn-sm btn-ghost" title="Remove"><?= icon('x', '', 12) ?></button></form></div><?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
