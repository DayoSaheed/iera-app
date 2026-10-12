<?php /** @var array $stats */ $showLocked = $showLocked ?? true; $earned = $stats['badges']; ?>
<div class="badge-grid">
  <?php foreach (\App\Services\Gamification::BADGES as $id => [$ico, $ttl, $desc]): $on = in_array($id, $earned, true); if (!$on && !$showLocked) continue; ?>
  <div class="badge-tile <?= $on ? 'is-on' : '' ?>" title="<?= e($desc) ?>"><span class="badge-ico"><?= icon($ico, '', 20) ?></span><span><?= e($ttl) ?></span></div>
  <?php endforeach; ?>
</div>
