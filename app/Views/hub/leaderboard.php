<?php $title = 'Leaderboard'; $active = 'leaderboard'; ?>
<p class="muted" style="margin-top:-6px">Top volunteers by impact.</p>
<form class="table-toolbar" method="get" action="<?= e(url('/leaderboard')) ?>">
  <select name="category" onchange="this.form.submit()"><option value="">All Categories</option><?php foreach (\App\Models\Application::CATEGORIES as $k => $v): ?><option value="<?= e($k) ?>" <?= $category === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select>
  <select name="sort" onchange="this.form.submit()"><option value="xp" <?= $sort === 'xp' ? 'selected' : '' ?>>Sort by XP</option><option value="hours" <?= $sort === 'hours' ? 'selected' : '' ?>>Sort by Hours</option><option value="reports" <?= $sort === 'reports' ? 'selected' : '' ?>>Sort by Reports</option></select>
</form>
<div class="card">
  <?php if (!$rows): ?><p class="empty"><?= icon('trophy', '', 28) ?><br>No approved volunteers yet in this category.</p><?php else: ?>
  <ol class="board">
    <?php foreach ($rows as $i => $r): $a = $r['app']; ?>
    <li class="board-row rank-<?= $i + 1 ?> <?= $a['user_id'] === $me['id'] ? 'is-me' : '' ?>">
      <span class="board-rank"><?= $i < 3 ? icon(['trophy', 'award', 'star'][$i], '', 20) : '#' . ($i + 1) ?></span>
      <span class="avatar"><?= e(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span>
      <span class="board-name"><a href="<?= e(url('/members/' . $a['id'])) ?>"><?= e($a['name']) ?></a><br><small><span class="pill" style="border-color:<?= e($r['level_color']) ?>;color:<?= e($r['level_color']) ?>">Lv.<?= $r['level'] ?> <?= e($r['level_title']) ?></span> <span class="muted"><?= e($a['volunteer_category']) ?></span></small></span>
      <span class="board-stat"><b><?= $r['xp'] ?></b><small>XP</small></span>
      <span class="board-stat"><b><?= rtrim(rtrim(number_format($r['hours'], 1), '0'), '.') ?>h</b><small>Hours</small></span>
      <span class="board-stat"><b><?= $r['reports'] ?></b><small>Reports</small></span>
      <span class="board-stat"><b><?= icon($r['streak'] > 0 ? 'flame' : 'clock', '', 14) ?> <?= $r['streak'] ?></b><small>Streak</small></span>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</div>
