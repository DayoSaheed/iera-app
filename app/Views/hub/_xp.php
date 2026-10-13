<?php /** @var array $stats */ $compact = $compact ?? false; ?>
<?php if ($compact): ?>
<div class="xp-compact"><span class="pill pill-accent">Lv.<?= $stats['level'] ?></span><div class="progress-bar grow"><span style="width:<?= $stats['progress'] ?>%"></span></div><strong><?= $stats['xp'] ?> XP</strong></div>
<?php else: ?>
<div class="xp-card">
  <div class="xp-head"><span class="xp-star" style="background:<?= e($stats['level_color']) ?>"><?= icon('star', '', 22) ?></span><div><div class="eyebrow">Level <?= $stats['level'] ?></div><strong><?= e($stats['level_title']) ?></strong></div><span class="grow"></span><div style="text-align:right"><div class="stat-value" style="font-size:1.6rem;margin:0"><?= $stats['xp'] ?></div><small class="muted">total XP</small></div></div>
  <div class="progress-bar" style="margin-top:10px"><span style="width:<?= $stats['progress'] ?>%"></span></div>
  <div class="small muted" style="margin-top:6px"><?= $stats['to_next'] ? $stats['to_next'] . ' XP to next level' : icon('award', '', 12) . ' Max Level Reached!' ?></div>
</div>
<?php endif; ?>
