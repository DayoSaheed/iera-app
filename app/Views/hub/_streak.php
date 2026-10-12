<?php /** @var array $stats */ $s = $stats['streak']; ?>
<div class="streak-card <?= $s >= 3 ? 'is-hot' : '' ?>">
  <div><div class="eyebrow">Current Streak</div><div class="streak-num"><?= icon($s > 0 ? 'flame' : 'clock', '', 26) ?> <?= $s ?></div><small class="muted"><?= $s === 1 ? 'week' : 'weeks' ?></small></div>
  <div class="streak-msg"><?= $s === 0 ? 'Submit a report<br>to start your streak!' : ($s < 3 ? "Don't break<br>your streak!" : 'On fire!<br>Keep going!') ?></div>
  <?php if ($s > 0): ?><div class="streak-bar"><?php for ($i = 0; $i < 8; $i++): ?><span class="<?= $i < min($s, 8) ? 'is-on' : '' ?>"></span><?php endfor; ?></div><?php endif; ?>
</div>
