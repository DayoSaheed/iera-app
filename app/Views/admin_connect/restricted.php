<?php $title = 'Restricted'; $active = 'cc-risk'; ?>
<div class="card" style="max-width:640px">
  <h2><?= icon('lock', '', 18) ?> Restricted area</h2>
  <p>This page is only available to <?= e($what) ?>. Your account (<?= e($u['email']) ?>) is not on the list.</p>
  <p class="small muted">A superadmin can add you under System admin → Settings → Onboarding (risk assessment reviewers), or set the risk assessment contact email to your address.</p>
  <a class="btn" href="<?= e(url('/admin')) ?>">Back to control centre</a>
</div>
