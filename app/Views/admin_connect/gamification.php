<?php $title = 'Badges & XP'; $active = 'cc-gamification'; ?>
<div class="stat-row" style="margin-bottom:16px">
  <div class="stat accent"><div class="stat-label">Badges awarded</div><div class="stat-value"><?= $stats['awards'] ?></div></div>
  <div class="stat plain"><div class="stat-label">Unique recipients</div><div class="stat-value"><?= $stats['recipients'] ?></div></div>
  <div class="stat primary"><div class="stat-label">XP awarded</div><div class="stat-value"><?= number_format($stats['xp']) ?></div></div>
  <div class="stat info"><div class="stat-label">Top level</div><div class="stat-value"><?= $stats['top'] ? 'Lv.' . $stats['top'] : '—' ?></div><div class="stat-sub"><?= $stats['active'] ?> active volunteers</div></div>
</div>
<div class="two-col">
  <div class="card">
    <div class="card-head"><h2>Volunteer leaderboard</h2><span class="grow"></span><form class="table-toolbar" style="margin:0" method="get" action="<?= e(url('/admin/gamification')) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search volunteers…"><button class="btn btn-sm btn-dark">Search</button></form></div>
    <?php if (!$board): ?><p class="empty">No approved volunteers yet.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Rank</th><th>Volunteer</th><th>Level / XP</th><th>Hours</th><th>Badges</th><th></th></tr></thead><tbody>
    <?php foreach ($board as $i => $r): $a = $r['app']; ?>
      <tr><td><strong>#<?= $i + 1 ?></strong></td><td><a href="<?= e(url('/admin/applications/' . $a['id'])) ?>"><strong><?= e($a['name']) ?></strong></a><br><span class="small muted"><?= e($a['volunteer_category']) ?></span></td>
        <td><span class="pill" style="border-color:<?= e($r['level_color']) ?>;color:<?= e($r['level_color']) ?>">Lv.<?= $r['level'] ?></span> <?= number_format($r['xp']) ?> XP</td><td><?= rtrim(rtrim(number_format($r['hours'], 1), '0'), '.') ?>h</td><td><?= icon('award', '', 14) ?> <?= count($r['badges']) ?></td>
        <td><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/gamification?award=' . $a['id'])) ?>#award">Award</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
  <div class="card-stack">
    <div class="card" id="award">
      <h2>Award a badge</h2>
      <form method="post" action="<?= e(url('/admin/gamification/award')) ?>"><?= csrf_field() ?>
        <div class="field"><label>Volunteer</label><select name="application_id" required><option value="">Choose…</option><?php foreach ($apps as $a): ?><option value="<?= e($a['id']) ?>" <?= $preselect === $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?> (<?= e($a['email']) ?>)</option><?php endforeach; ?></select></div>
        <div class="field"><label>Badge name</label><input name="badge" placeholder="e.g. Street Dawah Level 1" required></div>
        <div class="field"><label>Course / reason</label><input name="course_name" placeholder="e.g. Completed street dawah shadowing" required></div>
        <div class="field"><label>XP to award</label><input type="number" name="xp" value="50" min="0" max="5000"></div>
        <button class="btn btn-primary">Award badge</button>
      </form>
    </div>
    <div class="card">
      <div class="card-head"><h2>Recent badge awards</h2></div>
      <?php if (!$awards): ?><p class="empty">No badges awarded yet.</p><?php else: ?>
      <ul class="check-list"><?php foreach ($awards as $w): ?>
        <li style="display:flex;gap:10px;align-items:center"><span><strong><?= e($w['badge']) ?></strong><br><span class="small muted"><?= e($w['email'] ?: 'deleted user') ?> · <?= e($w['course_name']) ?> · +<?= (int) $w['xp_awarded'] ?> XP · <?= e(fmt_date($w['created_at'])) ?></span></span><span class="grow"></span>
          <form method="post" action="<?= e(url('/admin/gamification/awards/' . $w['id'] . '/delete')) ?>" data-confirm="Remove this badge?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" title="Remove"><?= icon('trash', '', 14) ?></button></form></li>
      <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
</div>
