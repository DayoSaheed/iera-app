<?php use App\Models\Community; $title = 'Story manager'; $active = 'cc-stories'; $badge = ['published' => 'approved', 'pending' => 'pending', 'hidden' => 'inactive']; ?>
<?php if ($syncResult): ?><div class="flash flash-info"><?= e($syncResult) ?></div><?php endif; ?>
<div class="card-head" style="margin-bottom:10px">
  <div class="actions"><?php foreach (['' => 'All', 'pending' => 'Pending', 'published' => 'Published', 'hidden' => 'Hidden'] as $k => $l): ?><a class="pill <?= $status === $k ? 'pill-accent' : '' ?>" href="<?= e(url('/admin/stories' . ($k ? '?status=' . $k : ''))) ?>"><?= $l ?> <strong><?= $counts[$k ?: 'all'] ?></strong></a><?php endforeach; ?></div>
  <span class="grow"></span>
  <a class="btn btn-sm" href="<?= e(url('/admin/stories?new=1')) ?>"><?= icon('plus', '', 14) ?> New story</a>
  <form method="post" action="<?= e(url('/admin/stories/sync')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= icon('refresh', '', 14) ?> Sync from <?= e(parse_url((string) \App\Models\Setting::get('connect_story_feed_url', 'https://global.iera.org'), PHP_URL_HOST) ?: 'iERA') ?></button></form>
</div>
<div class="<?= ($edit || $creating) ? 'two-col' : '' ?>">
  <div class="card">
    <p class="small muted">Manage global stories from iERA and volunteer submissions. Approving a volunteer story publishes it and awards the Storyteller badge (+30 XP).</p>
    <?php if (!$rows): ?><p class="empty">No stories in this list.</p><?php endif; ?>
    <?php foreach ($rows as $s): ?>
    <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--line)">
      <?php $img = $s['image_url'] ?: ($s['image_doc_id'] ? url('/documents/' . $s['image_doc_id']) : null); ?>
      <?php if ($img): ?><img src="<?= e($img) ?>" alt="" style="width:64px;height:48px;object-fit:cover;border-radius:6px"><?php else: ?><span style="width:64px;height:48px;border-radius:6px;background:var(--surface-2);display:inline-flex;align-items:center;justify-content:center"><?= icon('image', '', 18) ?></span><?php endif; ?>
      <div style="flex:1;min-width:0">
        <span class="badge badge-<?= e($badge[$s['status']] ?? 'default') ?>"><?= e($s['status']) ?></span><?= (int) $s['featured'] ? ' <span class="badge badge-pending">Featured</span>' : '' ?><?= $s['source'] === 'volunteer_submission' ? ' <span class="chip">Volunteer</span>' : '' ?>
        <div><a href="<?= e(url('/stories/' . $s['id'])) ?>" target="_blank"><strong><?= e($s['title']) ?></strong></a></div>
        <div class="small muted"><?= e(implode(' · ', array_filter([$s['continent'], $s['country'], $s['category'], $s['author'], fmt_date($s['published_date'])]))) ?></div>
      </div>
      <div class="actions">
        <?php $f = fn(string $a) => '<form method="post" action="' . e(url('/admin/stories/action')) . '">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $s['id'] . '"><input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="back" value="/admin/stories' . ($status ? '?status=' . $status : '') . '">'; ?>
        <?php if ($s['status'] === 'pending'): ?><?= $f('approve') ?><button class="btn btn-sm btn-primary">Approve</button></form><?php endif; ?>
        <?= $f('feature') ?><button class="btn btn-sm btn-ghost" title="<?= (int) $s['featured'] ? 'Unfeature' : 'Feature' ?>"><?= icon('star', '', 14) ?></button></form>
        <?php if ($s['status'] !== 'pending'): ?><?= $f($s['status'] === 'hidden' ? 'show' : 'hide') ?><button class="btn btn-sm btn-ghost"><?= $s['status'] === 'hidden' ? 'Show' : 'Hide' ?></button></form><?php endif; ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/stories?edit=' . $s['id'] . ($status ? '&status=' . $status : ''))) ?>">Edit</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($edit): ?>
  <div class="card">
    <div class="card-head"><h2>Edit story</h2><span class="grow"></span><form method="post" action="<?= e(url('/admin/stories/action')) ?>" data-confirm="Delete this story?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-ghost"><?= icon('trash', '', 14) ?> Delete</button></form></div>
    <form method="post" action="<?= e(url('/admin/stories/action')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="action" value="save">
      <div class="field"><label>Title</label><input name="title" value="<?= e($edit['title']) ?>" required></div>
      <div class="grid-2">
        <div class="field"><label>Continent</label><select name="continent"><?php foreach (Community::CONTINENTS as $c): ?><option <?= $edit['continent'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Country</label><input name="country" value="<?= e($edit['country']) ?>"></div>
        <div class="field"><label>Category</label><select name="category"><?php foreach (Community::STORY_CATEGORIES as $c): ?><option <?= $edit['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Status</label><select name="status"><?php foreach (['published', 'pending', 'hidden'] as $c): ?><option <?= $edit['status'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Excerpt</label><textarea name="excerpt" rows="3"><?= e($edit['excerpt']) ?></textarea></div>
      <label class="check" style="margin-bottom:12px"><input type="checkbox" name="featured" value="1" <?= (int) $edit['featured'] ? 'checked' : '' ?>> Featured story</label>
      <div class="actions"><button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="<?= e(url('/admin/stories')) ?>">Cancel</a></div>
    </form>
  </div>
  <?php elseif ($creating): ?>
  <div class="card">
    <h2>Create new story</h2>
    <form method="post" action="<?= e(url('/admin/stories/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="field"><label>Title</label><input name="title" required></div>
      <div class="grid-2">
        <div class="field"><label>Author</label><input name="author" placeholder="iERA"></div>
        <div class="field"><label>Country</label><input name="country"></div>
        <div class="field"><label>Continent</label><select name="continent"><?php foreach (Community::CONTINENTS as $c): ?><option <?= $c === 'Global' ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Category</label><select name="category"><?php foreach (Community::STORY_CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Source URL</label><input name="source_url" placeholder="https://"></div>
        <div class="field"><label>Featured image URL</label><input name="image_url" placeholder="https://"></div>
      </div>
      <div class="field"><label>Excerpt</label><textarea name="excerpt" rows="2"></textarea></div>
      <div class="field"><label>Story text (optional, HTML allowed)</label><textarea name="content" rows="6"></textarea></div>
      <div class="actions"><button class="btn btn-primary">Publish story</button><a class="btn btn-ghost" href="<?= e(url('/admin/stories')) ?>">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>
</div>
