<?php
use App\Models\Application;
$title = 'Onboarding · ' . Application::STAGES[$stage][0];
$flash = $_SESSION['stage_flash'] ?? null; unset($_SESSION['stage_flash']);
$flashText = [1 => 'Perfect, we have the basics. Keep going.', 2 => 'JazakAllah khair for sharing your background.', 3 => 'Great — we know exactly how to put you to good use.', 4 => 'Almost there — one final step.', 5 => 'You are all set. Welcome to the iERA volunteer family.'];
$uk = Application::isUk($app); $online = Application::isOnline($app);
$v = fn(string $k) => e(old($k, (string) ($app[$k] ?? '')));
$yn = function (string $k) use ($app) { $val = $app[$k]; $o = old($k, $val === null ? '' : ($val ? '1' : '0')); return $o; };
$err = fn(string $k) => isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
?>
<?php if ($flash && isset($flashText[$flash])): ?><div class="ob-flash" data-ob-flash><div><?= icon('check-circle', '', 40) ?><p><?= e($flashText[$flash]) ?></p></div></div><?php endif; ?>
<div class="ob-wide">
  <ol class="ob-progress">
    <?php foreach (Application::STAGES as $n => [$label, $ico, $xp]): $done = $n < $stage || ($n < $app['onboarding_stage'] && $n !== $stage) || ($n === 4 && Application::agreementComplete($app)); ?>
    <li class="<?= $n === $stage ? 'is-active' : ($done ? 'is-done' : '') ?>"><?php if ($n <= $app['onboarding_stage'] && $n !== 4): ?><a href="<?= e(url('/onboarding?stage=' . $n)) ?>"><?php else: ?><span><?php endif; ?><span class="ob-prog-num"><?= $done ? icon('check', '', 12) : $n ?></span><span class="ob-prog-label"><?= e($label) ?></span><?= $n <= $app['onboarding_stage'] && $n !== 4 ? '</a>' : '</span>' ?></li>
    <?php endforeach; ?>
  </ol>
  <p class="ob-autosave small muted"><?= icon('check', 'icon-sm', 14) ?> <span data-autosave-text>Your progress is saved automatically — you can return anytime to continue.</span></p>

  <div class="ob-stage-head">
    <span class="ob-stage-icon"><?= icon(Application::STAGES[$stage][1], '', 22) ?></span>
    <div><h1><?= e(Application::STAGES[$stage][0]) ?></h1>
      <?php if ($stage === 4): ?><p class="muted">These agreements protect you, the people you serve, and the integrity of iERA's work.</p><?php elseif ($stage === 5): ?><p class="muted">Take a moment to review everything before submitting — once submitted your application will be reviewed by our team.</p><?php endif; ?></div>
    <?php if (Application::STAGES[$stage][2]): ?><span class="pill pill-accent"><?= icon('zap', '', 12) ?> +<?= Application::STAGES[$stage][2] ?> XP</span><?php endif; ?>
  </div>

  <?php if ($stage <= 3): ?>
  <form method="post" action="<?= e(url('/onboarding/' . $stage)) ?>" class="ob-form" novalidate data-wizard data-autosave="<?= e(url('/onboarding/autosave')) ?>">
    <?= csrf_field() ?><input type="hidden" name="from" value="<?= $editing ? 'review' : '' ?>">
    <?php include __DIR__ . '/_stage' . $stage . '.php'; ?>
    <div class="ob-nav">
      <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('/onboarding?stage=5')) ?>"><?= icon('chevron-left', '', 16) ?> Back to Review</a><?php elseif ($stage > 1): ?><a class="btn btn-ghost" href="<?= e(url('/onboarding?stage=' . ($stage - 1))) ?>"><?= icon('chevron-left', '', 16) ?> Back</a><?php else: ?><span></span><?php endif; ?>
      <button class="btn btn-primary btn-lg"><?= $editing ? icon('check', '', 16) . ' Save & Return to Review' : 'Save & Continue ' . icon('chevron-right', '', 16) ?></button>
    </div>
  </form>
  <?php elseif ($stage === 4): ?>
    <?php include __DIR__ . '/_stage4.php'; ?>
  <?php else: ?>
    <?php include __DIR__ . '/_stage5.php'; ?>
  <?php endif; ?>
</div>
