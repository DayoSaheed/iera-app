<?php $title = 'Member Directory'; $active = 'members'; ?>
<p class="muted" style="margin-top:-6px"><?= count($rows) ?> approved volunteers in our community.</p>
<form class="table-toolbar" method="get" action="<?= e(url('/members')) ?>">
  <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search by name, city, country…">
  <select name="category"><option value="">All categories</option><?php foreach (\App\Models\Application::CATEGORIES as $k => $v): ?><option value="<?= e($k) ?>" <?= $category === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select>
  <select name="skill"><option value="">All Skills</option><?php foreach ($skills as $s): ?><option <?= $skill === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
  <button class="btn btn-sm btn-dark">Filter</button>
</form>
<?php if (!$rows): ?><div class="card"><p class="empty">No members found. Try adjusting your filters.</p></div><?php else: ?>
<div class="grid-3">
  <?php foreach ($rows as $r): $a = $r['app']; ?>
  <a class="member-card" href="<?= e(url('/members/' . $a['id'])) ?>">
    <div class="member-cover" style="background:<?= e($r['level_color']) ?>"></div>
    <span class="avatar member-avatar"><?= e(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span>
    <strong><?= e($a['name']) ?></strong>
    <span class="pill" style="border-color:<?= e($r['level_color']) ?>;color:<?= e($r['level_color']) ?>"><?= e($r['level_title']) ?></span>
    <small class="muted"><?= e(trim(($a['city'] ?? '') . ', ' . ($a['country'] ?? ''), ', ')) ?></small>
    <div class="member-stats"><span><?= icon('zap', '', 12) ?> <?= $r['xp'] ?> XP</span><span><?= icon('clock', '', 12) ?> <?= rtrim(rtrim(number_format($r['hours'], 1), '0'), '.') ?>h</span></div>
    <div class="choices"><span class="chip"><?= e($a['volunteer_category']) ?></span><?php if ($a['skills']): ?><span class="chip"><?= e($a['skills'][0]) ?></span><?php endif; ?></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
