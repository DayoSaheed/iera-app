<?php use App\Models\Application; $title = 'Application submitted'; $uk = Application::isUk($app); $training = array_intersect($app['role_interests'], ['Street Dawah', 'Exhibitions']); ?>
<div class="ob-narrow ob-center">
  <div class="ob-moon" style="background:var(--success);color:#fff"><?= icon('check', '', 34) ?></div>
  <h1>Application submitted!</h1>
  <p class="muted">JazakAllah khair — our team will review your application within <?= e(\App\Models\Setting::get('connect_review_days')) ?>.</p>
  <?php if ($uk && empty($app['call_scheduled'])): ?>
  <div class="card ob-card" style="text-align:left"><h2><?= icon('calendar', '', 18) ?> Book your onboarding interview</h2><p class="muted">You're one step away from completing onboarding. This is a short phone interview — pick a date and time that works for you, and a member of the volunteer onboarding team will phone you then.</p><a class="btn btn-primary" href="<?= e(url('/book-interview')) ?>"><?= icon('calendar', '', 16) ?> Book my interview</a></div>
  <?php endif; ?>
  <?php if ($uk && $training && $dawahCourse): ?>
  <div class="card ob-card" style="text-align:left"><h2><?= icon('graduation-cap', '', 18) ?> Sign up for Dawah training</h2><p class="muted">As a Street Dawah / Exhibitions volunteer, complete the iERA Dawah training course to get started.</p><a class="btn btn-ghost" href="<?= e($dawahCourse) ?>" target="_blank" rel="noopener"><?= icon('external-link', '', 14) ?> Sign up for training</a></div>
  <?php endif; ?>
  <p style="margin-top:18px"><a class="btn btn-dark btn-lg" href="<?= e(url('/dashboard')) ?>"><?= icon('home', '', 16) ?> <?= $uk ? 'Skip for now and go to dashboard' : 'Go to dashboard' ?></a></p>
</div>
