<?php $title = 'Moderation'; $active = 'cc-moderation'; ?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach (['posts' => 'Posts', 'hidden' => 'Hidden', 'comments' => 'Comments'] as $k => $l): ?><a class="tab <?= $tab === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/moderation?tab=' . $k)) ?>"><?= $l ?> <span class="nav-count" style="margin-left:6px"><?= $counts[$k] ?></span></a><?php endforeach; ?></div>
  <span class="grow"></span>
  <?php if ($openIssues): ?><a class="pill pill-accent" href="<?= e(url('/admin/feedback?status=New')) ?>"><?= icon('alert-triangle', '', 14) ?> <?= $openIssues ?> new issue report(s)</a><?php endif; ?>
</div>
<div class="card">
  <?php if ($tab === 'comments'): ?>
    <?php if (!$flat): ?><p class="empty">No comments yet.</p><?php endif; ?>
    <?php foreach ($flat as $c): ?><div class="comment" style="display:flex;gap:8px;align-items:flex-start"><span style="flex:1"><strong><?= e($c['author_name']) ?>:</strong> <?= e($c['content']) ?> <span class="small muted"><?= e(fmt_datetime($c['created_at'])) ?></span></span><form method="post" action="<?= e(url('/admin/feed/action')) ?>" data-confirm="Delete this comment?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="back" value="/admin/moderation?tab=comments"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 12) ?></button></form></div><?php endforeach; ?>
  <?php else: $list = $tab === 'hidden' ? $hidden : $visible; ?>
    <?php if (!$list): ?><p class="empty"><?= $tab === 'hidden' ? 'No hidden posts.' : 'No posts to moderate.' ?></p><?php endif; ?>
    <?php foreach ($list as $p): ?>
    <div class="post" style="border:1px solid var(--line);border-radius:12px;margin-bottom:10px<?= $tab === 'hidden' ? ';border-color:var(--danger)' : '' ?>">
      <div class="post-head"><div style="flex:1"><strong><?= e($p['author_name']) ?></strong> <span class="small muted"><?= e(fmt_datetime($p['created_at'])) ?></span> <span class="chip"><?= e($p['type']) ?></span><div class="post-content" style="margin-top:6px"><?= e(mb_strimwidth($p['content'], 0, 240, '…')) ?></div><div class="small muted" style="margin-top:6px"><?= count($p['likes']) ?> likes · <?= count($comments[$p['id']] ?? []) ?> comments</div></div>
        <div class="actions">
          <form method="post" action="<?= e(url('/admin/feed/action')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="<?= $tab === 'hidden' ? 'show' : 'hide' ?>"><input type="hidden" name="back" value="/admin/moderation?tab=<?= e($tab) ?>"><button class="btn btn-sm <?= $tab === 'hidden' ? 'btn-primary' : '' ?>"><?= $tab === 'hidden' ? 'Restore' : 'Hide' ?></button></form>
          <form method="post" action="<?= e(url('/admin/feed/action')) ?>" data-confirm="Permanently delete this post?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="back" value="/admin/moderation?tab=<?= e($tab) ?>"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form>
        </div></div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
