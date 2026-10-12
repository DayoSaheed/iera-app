<?php use App\Models\Community; $title = 'Community Feed'; $active = 'community'; $initials = fn($n) => mb_strtoupper(mb_substr($n, 0, 1)); ?>
<p class="muted" style="margin-top:-6px">Share updates, celebrate wins, and connect with the ummah.</p>
<div class="two-col">
  <div class="card-stack">
    <form method="post" action="<?= e(url('/community/post')) ?>" enctype="multipart/form-data" class="card compose"><?= csrf_field() ?>
      <div style="display:flex;gap:12px"><span class="avatar"><?= e($initials($app['name'] ?? $u['email'])) ?></span><textarea name="content" rows="3" placeholder="Share something with the community…" style="flex:1"></textarea></div>
      <div class="actions" style="margin-top:10px"><label class="btn btn-sm btn-ghost"><?= icon('image', '', 14) ?> Add Photos<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden data-count-files></label><span class="small muted" data-file-count>Up to 6 photos, 8 MB each</span><span class="grow"></span><button class="btn btn-primary btn-sm"><?= icon('send', '', 14) ?> Post</button></div>
    </form>
    <?php if (!$posts): ?><div class="card" style="text-align:center;padding:36px"><h2>Be the first to post!</h2><p class="muted">Share what you've been up to with the community</p></div><?php endif; ?>
    <?php foreach ($posts as $p): $liked = in_array($u['id'], $p['likes'], true); $pc = $comments[$p['id']] ?? []; ?>
    <article class="card post type-<?= e($p['type']) ?>" id="post-<?= (int) $p['id'] ?>">
      <?php if ($p['pinned']): ?><div class="post-banner"><?= icon('pin', '', 12) ?> Pinned Post</div><?php elseif ($p['featured']): ?><div class="post-banner is-featured"><?= icon('star', '', 12) ?> Featured</div><?php endif; ?>
      <div class="post-head"><span class="avatar"><?= e($initials($p['author_name'])) ?></span><div><strong><?= e($p['author_name']) ?></strong><?php if (Community::POST_TYPES[$p['type']] ?? null): ?> <span class="chip"><?= icon(Community::POST_TYPES[$p['type']], '', 11) ?> <?= e($p['type']) ?></span><?php endif; ?><br><small class="muted"><?= e(fmt_datetime($p['created_at'])) ?></small></div><span class="grow"></span>
        <?php if ($isAdmin || $p['user_id'] === $u['id']): ?><form method="post" action="<?= e(url('/community/post/' . $p['id'])) ?>" class="actions"><?= csrf_field() ?>
          <?php if ($isAdmin): ?><button class="btn btn-sm btn-ghost" name="action" value="pin"><?= $p['pinned'] ? 'Unpin' : 'Pin' ?></button><button class="btn btn-sm btn-ghost" name="action" value="feature"><?= $p['featured'] ? 'Unfeature' : 'Feature' ?></button><button class="btn btn-sm btn-ghost" name="action" value="hide">Hide</button><?php endif; ?>
          <button class="btn btn-sm btn-ghost" name="action" value="delete" data-confirm="Delete this post?"><?= icon('trash', '', 13) ?></button></form><?php endif; ?></div>
      <?php if ($p['content']): ?><p class="post-content"><?= nl2br(e($p['content'])) ?></p><?php endif; ?>
      <?php if ($p['images']): ?><div class="post-images"><?php foreach ($p['images'] as $img): ?><a href="<?= e(url('/documents/' . $img)) ?>" target="_blank"><img src="<?= e(url('/documents/' . $img)) ?>" alt="" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
      <div class="post-actions">
        <form method="post" action="<?= e(url('/community/post/' . $p['id'])) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm <?= $liked ? 'btn-primary' : 'btn-ghost' ?>" name="action" value="like"><?= icon('heart', '', 14) ?> <?= count($p['likes']) ?: '' ?></button></form>
        <button class="btn btn-sm btn-ghost" type="button" data-toggle-target="comments-<?= (int) $p['id'] ?>"><?= icon('message-square', '', 14) ?> Comments<?= $pc ? ' (' . count($pc) . ')' : '' ?></button>
      </div>
      <div class="post-comments" id="comments-<?= (int) $p['id'] ?>" <?= $pc ? '' : 'hidden' ?>>
        <?php foreach ($pc as $c): ?><div class="comment"><strong><?= e($c['author_name']) ?></strong> <small class="muted"><?= e(fmt_datetime($c['created_at'])) ?></small><br><?= nl2br(e($c['content'])) ?></div><?php endforeach; ?>
        <form method="post" action="<?= e(url('/community/post/' . $p['id'])) ?>" class="actions" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="comment"><input class="input" name="content" placeholder="Write a comment…" required style="flex:1"><button class="btn btn-sm btn-dark"><?= icon('send', '', 13) ?></button></form>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <div class="card-stack">
    <div class="card"><div class="eyebrow">Your Level</div><?= \App\Core\View::partial('hub/_xp', ['stats' => $stats]) ?><a class="small" href="<?= e(url('/leaderboard')) ?>">View Leaderboard <?= icon('chevron-right', '', 12) ?></a></div>
    <?php if ($activity): ?><div class="card"><h2><?= icon('zap', '', 14) ?> Recent Activity</h2><ul class="activity"><?php foreach ($activity as $a): ?><li><?= icon($a['icon'], '', 14) ?> <strong><?= e($a['user']) ?></strong> <?= e($a['text']) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="card"><div class="card-head"><h2>My Groups</h2><span class="grow"></span><a class="small" href="<?= e(url('/groups')) ?>">All groups</a></div>
      <ul class="check-list"><?php foreach (array_slice(Community::GROUPS, 0, 6, true) as $slug => [$name, $ico]): $in = in_array($slug, $memberships, true); ?><li><span><?= icon($ico, '', 14) ?> <?= e($name) ?></span><form method="post" action="<?= e(url('/groups/' . $slug . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $in ? 'btn-dark' : 'btn-ghost' ?>"><?= $in ? 'Joined' : 'Join' ?></button></form></li><?php endforeach; ?></ul></div>
    <div class="card"><div class="card-head"><h2>Upcoming Events</h2><span class="grow"></span><a class="small" href="<?= e(url('/events')) ?>">See all</a></div>
      <?php if (!$upcoming): ?><p class="muted small">No upcoming events yet</p><?php else: ?><ul class="check-list"><?php foreach ($upcoming as $ev): ?><li><span><?= icon(Community::EVENT_TYPES[$ev['event_type']][1] ?? 'calendar', '', 14) ?> <?= e($ev['title']) ?></span><small class="muted"><?= e(fmt_date($ev['event_date'])) ?></small></li><?php endforeach; ?></ul><?php endif; ?></div>
    <div class="card"><h2>Quick Links</h2><ul class="check-list"><li><a href="<?= e(url('/members')) ?>">Member Directory</a></li><li><a href="<?= e(url('/groups')) ?>">Groups</a></li><li><a href="<?= e(url('/events')) ?>">Events</a></li><li><a href="<?= e(url('/stories')) ?>">Story Hub</a></li><li><a href="<?= e(url('/profile?tab=edit')) ?>">Edit Profile</a></li></ul></div>
  </div>
</div>
