<?php use App\Models\Community; $title = 'Community feed'; $active = 'cc-feed'; ?>
<div class="card">
  <div class="card-head"><h2>Community feed</h2><span class="small muted"><?= $totalPosts ?> posts · <?= $totalComments ?> comments</span><span class="grow"></span>
    <details><summary class="btn btn-sm btn-primary" style="list-style:none"><?= icon('plus', '', 14) ?> Create post</summary>
      <form method="post" action="<?= e(url('/admin/feed/action')) ?>" style="margin-top:10px;min-width:320px"><?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="field"><select name="type"><?php foreach (['update' => 'Update', 'story' => 'Story', 'achievement' => 'Achievement', 'milestone' => 'Milestone'] as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="field"><textarea name="content" rows="4" placeholder="Write your post…" required></textarea></div>
        <button class="btn btn-sm btn-primary">Post</button>
      </form></details>
  </div>
  <form class="table-toolbar" method="get" action="<?= e(url('/admin/feed')) ?>">
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search posts…">
    <select name="type"><option value="">All types</option><?php foreach (array_keys(Community::POST_TYPES) as $t): ?><option <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
    <select name="visibility"><?php foreach (['visible' => 'Visible', 'hidden' => 'Hidden', 'all' => 'All posts'] as $k => $v): ?><option value="<?= $k ?>" <?= $visibility === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-dark">Filter</button>
  </form>
  <?php if (!$rows): ?><p class="empty">No posts found.</p><?php endif; ?>
  <?php foreach ($rows as $p): $cs = $comments[$p['id']] ?? []; ?>
  <div class="post type-<?= e($p['type']) ?>" style="border:1px solid var(--line);border-radius:12px;margin-bottom:12px<?= (int) $p['hidden'] ? ';background:var(--danger-50)' : '' ?>">
    <div class="post-head"><span class="avatar"><?= e(mb_strtoupper(mb_substr($p['author_name'] ?: '?', 0, 1))) ?></span><div style="flex:1"><strong><?= e($p['author_name']) ?></strong> <span class="small muted"><?= e(fmt_datetime($p['created_at'])) ?> · <?= e($p['type']) ?></span><?= (int) $p['hidden'] ? ' <span class="badge badge-inactive">Hidden</span>' : '' ?><?= (int) $p['pinned'] ? ' <span class="badge badge-pending">Pinned</span>' : '' ?></div>
      <div class="actions">
        <form method="post" action="<?= e(url('/admin/feed/action')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="<?= (int) $p['hidden'] ? 'show' : 'hide' ?>"><input type="hidden" name="back" value="/admin/feed?visibility=<?= e($visibility) ?>"><button class="btn btn-sm btn-ghost"><?= (int) $p['hidden'] ? 'Show' : 'Hide' ?></button></form>
        <form method="post" action="<?= e(url('/admin/feed/action')) ?>" data-confirm="Delete this post permanently?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="back" value="/admin/feed?visibility=<?= e($visibility) ?>"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?></button></form>
      </div></div>
    <div class="post-content"><?= e($p['content']) ?></div>
    <?php if ($p['images']): ?><div class="post-images"><?php foreach ($p['images'] as $img): ?><img src="<?= e(url('/documents/' . $img)) ?>" alt="" style="border-radius:8px;width:100%"><?php endforeach; ?></div><?php endif; ?>
    <div class="small muted" style="margin-top:8px"><?= count($p['likes']) ?> likes · <?= count($cs) ?> comments</div>
    <?php if ($cs): ?><div class="post-comments"><?php foreach ($cs as $c): ?><div class="comment" style="display:flex;gap:8px;align-items:flex-start"><span style="flex:1"><strong><?= e($c['author_name']) ?></strong> <?= e($c['content']) ?></span><form method="post" action="<?= e(url('/admin/feed/action')) ?>" data-confirm="Delete this comment?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="back" value="/admin/feed?visibility=<?= e($visibility) ?>"><button class="btn btn-sm btn-ghost" title="Delete comment"><?= icon('x', '', 12) ?></button></form></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
