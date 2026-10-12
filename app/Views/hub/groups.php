<?php $title = 'Groups & Communities'; $active = 'community'; ?>
<p class="muted" style="margin-top:-6px">Join the groups that match your role and region.</p>
<div class="grid-3">
  <?php foreach (\App\Models\Community::GROUPS as $slug => [$name, $ico, $desc]): $in = in_array($slug, $memberships, true); $n = $counts[$slug] ?? 0; ?>
  <div class="region-card"><div class="region-head"><span class="region-code"><?= icon($ico, '', 18) ?></span><div><div class="region-title"><?= e($name) ?></div><div class="region-sub"><?= $n ?> member<?= $n === 1 ? '' : 's' ?></div></div></div>
    <div style="padding:12px 16px"><p class="small muted" style="margin:0 0 10px"><?= e($desc) ?></p><form method="post" action="<?= e(url('/groups/' . $slug . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $in ? 'btn-dark' : 'btn-primary' ?>"><?= $in ? icon('check', '', 12) . ' Joined' : 'Join Group' ?></button></form></div></div>
  <?php endforeach; ?>
</div>
