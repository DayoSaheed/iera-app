<ol class="stepper-track auth-steps" aria-label="Setup progress">
<?php foreach ($steps as $i => $label): ?>
  <li class="stepper-item <?= $i < $current ? 'is-done' : ($i === $current ? 'is-active' : '') ?>"><span class="stepper-num"><?= $i < $current ? icon('check', '', 14) : $i + 1 ?></span><span class="stepper-label"><?= e($label) ?></span></li>
<?php endforeach; ?>
</ol>
