<div class="error-page">
  <div class="error-code"><?= (int) $status ?></div>
  <h1><?= e($message) ?></h1>
  <p class="muted">
    <?php if ($status === 404): ?>The page you are looking for does not exist or was moved.<?php elseif ($status === 403): ?>If you believe you should have access, please contact your team lead or the admin team.<?php elseif ($status === 503): ?>Thank you for your patience.<?php else: ?>Please try again. If the problem persists, contact support.<?php endif; ?>
  </p>
  <p><a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to the home page</a></p>
</div>
