<?php /** @var array $steps @var int $current */ ?>
<ol class="stepper-track auth-steps" aria-label="Sign-in progress">
<?php foreach ($steps as $i => $label): ?>
  <li class="stepper-item <?= $i < $current ? 'is-done' : ($i === $current ? 'is-active' : '') ?>"><span class="stepper-num"><?= $i < $current ? '✓' : $i + 1 ?></span><span class="stepper-label"><?= e($label) ?></span></li>
<?php endforeach; ?>
</ol>
