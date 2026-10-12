<?php $title = $app['name']; $active = 'members'; $breadcrumbs = ['Members' => '/members', $title => null]; ?>
<div class="hero">
  <span class="avatar" style="width:64px;height:64px;font-size:1.5rem"><?= e(mb_strtoupper(mb_substr($app['name'], 0, 1))) ?></span>
  <div><h1><?= e($app['name']) ?></h1><p><span class="chip" style="border-color:var(--accent);color:var(--accent);background:transparent">Level <?= $stats['level'] ?> · <?= e($stats['level_title']) ?></span> <span class="chip" style="border-color:rgba(255,255,255,.4);color:#fff;background:transparent"><?= e($app['volunteer_category']) ?></span> <?php if ($app['city'] || $app['country']): ?><small><?= icon('map-pin', '', 12) ?> <?= e(trim(($app['city'] ?? '') . ', ' . ($app['country'] ?? ''), ', ')) ?></small><?php endif; ?></p><?php if ($app['motivation']): ?><p style="font-style:italic">“<?= e(mb_strlen($app['motivation']) > 200 ? mb_substr($app['motivation'], 0, 200) . '…' : $app['motivation']) ?>”</p><?php endif; ?></div>
  <span class="grow"></span>
  <?php if ($own): ?><a class="btn btn-light" href="<?= e(url('/profile?tab=edit')) ?>">Edit Profile</a><?php else: ?><a class="btn btn-accent" href="#message"><?= icon('message-circle', '', 14) ?> Message</a><?php endif; ?>
</div>
<div class="stat-row" style="margin-bottom:16px">
  <div class="stat accent"><div class="stat-label">XP</div><div class="stat-value"><?= $stats['xp'] ?></div></div>
  <div class="stat plain"><div class="stat-label">Hours</div><div class="stat-value"><?= rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.') ?></div></div>
  <div class="stat plain"><div class="stat-label">Reports</div><div class="stat-value"><?= $stats['reports'] ?></div></div>
  <div class="stat primary"><div class="stat-label">Streak</div><div class="stat-value"><?= $stats['streak'] ?></div></div>
</div>
<div class="two-col">
  <div class="card-stack">
    <div class="card"><div class="card-head"><h2>Badges (<?= count($stats['badges']) ?>/<?= count(\App\Services\Gamification::BADGES) ?>)</h2></div><?= \App\Core\View::partial('hub/_badges', ['stats' => $stats]) ?></div>
    <?php if ($app['skills'] || $app['languages']): ?><div class="card"><h2>Skills & Languages</h2><div class="choices"><?php foreach ($app['skills'] as $s): ?><span class="chip"><?= e($s) ?></span><?php endforeach; ?><?php foreach ($app['languages'] as $s): ?><span class="chip" style="border-color:var(--primary);color:var(--primary)"><?= e($s) ?></span><?php endforeach; ?></div></div><?php endif; ?>
    <?php if ($stats['completions']): ?><div class="card"><h2>Training Progress (<?= $stats['completions'] ?>/<?= $stats['modules'] ?> modules)</h2><div class="progress-bar"><span style="width:<?= $stats['modules'] ? min(100, (int) round($stats['completions'] / $stats['modules'] * 100)) : 100 ?>%"></span></div><p class="small muted" style="margin:6px 0 0"><?= $stats['completions'] ?> missions completed</p></div><?php endif; ?>
    <?php if ($stories): ?><div class="card"><h2>Stories Submitted (<?= count($stories) ?>)</h2><ul class="check-list"><?php foreach ($stories as $s): ?><li><a href="<?= e(url('/stories/' . $s['id'])) ?>"><?= e($s['title']) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$own): ?><form method="post" action="<?= e(url('/messages')) ?>" class="card" id="message"><?= csrf_field() ?><input type="hidden" name="to_user_id" value="<?= e($app['user_id']) ?>"><h2>Send a Message</h2><div class="actions"><input class="input" name="content" placeholder="Message <?= e($app['first_name'] ?: $app['name']) ?>…" required style="flex:1"><button class="btn btn-primary"><?= icon('send', '', 14) ?> Send</button></div></form><?php endif; ?>
  </div>
  <div class="card-stack">
    <?php if ($app['role_interests']): ?><div class="card"><h2>Role Interests</h2><ul class="check-list"><?php foreach ($app['role_interests'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($reports): ?><div class="card"><h2>Recent activity</h2><ul class="check-list"><?php foreach ($reports as $r): ?><li><span><?= e($r['activity_type']) ?></span><small class="muted"><?= e(fmt_date($r['report_date'])) ?> · <?= rtrim(rtrim(number_format((float) $r['hours'], 1), '0'), '.') ?>h</small></li><?php endforeach; ?></ul></div><?php endif; ?>
  </div>
</div>
