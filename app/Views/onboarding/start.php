<?php $title = 'Welcome'; ?>
<div class="ob-narrow ob-center">
  <div class="ob-moon"><?= icon('star', '', 34) ?></div>
  <div class="eyebrow">iERA Volunteers</div>
  <h1>Every great dawah journey<br>begins with a single step.</h1>
  <p class="muted">You're joining a family of volunteers dedicated to sharing the message of Islam with the world. Let's get you set up.</p>
  <span class="pill"><?= icon('clock', '', 14) ?> This takes around 5 minutes</span>
  <div class="card ob-card" style="text-align:left;margin-top:22px">
    <h2>What we'll cover</h2>
    <ul class="ob-cover">
      <li><span class="ob-cover-icon"><?= icon('user', '', 18) ?></span><div><strong>About you</strong><small>Tell us who you are and where you're based</small></div></li>
      <li><span class="ob-cover-icon"><?= icon('globe', '', 18) ?></span><div><strong>Your dawah journey</strong><small>Share your background and experience</small></div></li>
      <li><span class="ob-cover-icon"><?= icon('wrench', '', 18) ?></span><div><strong>Your skills</strong><small>Help us match you with the right opportunities</small></div></li>
      <li><span class="ob-cover-icon"><?= icon('clipboard', '', 18) ?></span><div><strong>Your pledge</strong><small>Review and agree to our volunteer terms</small></div></li>
      <li><span class="ob-cover-icon"><?= icon('check-circle', '', 18) ?></span><div><strong>Activation</strong><small>Review your application and submit for approval</small></div></li>
    </ul>
  </div>
  <a class="btn btn-primary btn-lg" href="<?= e(url('/onboarding?stage=1')) ?>">Let's get started <?= icon('chevron-right', '', 16) ?></a>
</div>
