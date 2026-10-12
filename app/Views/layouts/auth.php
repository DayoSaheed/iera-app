<?php
use App\Core\Config;
$pageTitle = $title ?? 'Sign in';
$__bg = \App\Models\Setting::uploadedFile('login_background');
$__b = \App\Models\Setting::brand();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?> · <?= e(Config::get('app.name')) ?></title>
<link rel="icon" href="<?= e(\App\Models\Setting::logoUrl()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
<?php foreach (glob(BASE_PATH . '/public/assets/css/modules/*.css') ?: [] as $moduleCss): ?><link rel="stylesheet" href="<?= e(url('/assets/css/modules/' . basename($moduleCss) . '?v=' . APP_VERSION)) ?>"><?php endforeach; ?>
<style>:root{--primary:<?= e($__b['primary']) ?>;--primary-600:<?= e($__b['primary_600']) ?>;--primary-50:<?= e($__b['primary_50']) ?>;--accent:<?= e($__b['accent']) ?>;--accent-50:<?= e($__b['accent_50']) ?>;--sidebar:<?= e($__b['dark']) ?>;}</style>
</head>
<body class="auth">
<div class="auth-bg <?= $__bg ? 'has-image' : '' ?>" <?= $__bg ? 'style="--login-image:url(\'' . e(url($__bg)) . '\')"' : '' ?>></div>
<main class="auth-split">
  <aside class="auth-side <?= $__bg ? 'has-image' : '' ?>" <?= $__bg ? 'style="--login-image:url(\'' . e(url($__bg)) . '\')"' : '' ?>>
    <div class="auth-brand">
      <img src="<?= e(\App\Models\Setting::logoUrl()) ?>" alt="" height="56">
      <div><strong><?= e(Config::get('app.name')) ?></strong><small><?= e(\App\Models\Setting::get('org_name')) ?></small></div>
    </div>
    <div>
      <h2><?= e(\App\Models\Setting::get('login_tagline') ?: 'Connecting humanity with God.') ?></h2>
      <p>One place for da'ees, mentors, team leads, new Muslims and partner organizations.</p>
      <ul class="auth-points">
        <li><?= icon('calendar', '', 18) ?> Register for dawah events and track attendance</li>
        <li><?= icon('clipboard', '', 18) ?> Submit event and mentor reports in a minute</li>
        <li><?= icon('heart-handshake', '', 18) ?> Mentor new Muslims through the handbook</li>
        <li><?= icon('shield', '', 18) ?> Secure sign-in with emailed codes</li>
      </ul>
    </div>
    <div class="auth-side-foot">&copy; <?= date('Y') ?> <?= e(\App\Models\Setting::get('org_name')) ?><?php if (\App\Models\Setting::get('support_email')): ?> · <a href="mailto:<?= e(\App\Models\Setting::get('support_email')) ?>" style="color:inherit"><?= e(\App\Models\Setting::get('support_email')) ?></a><?php endif; ?></div>
  </aside>
  <section class="auth-form">
    <div class="card auth-card">
      <?= \App\Core\View::partial('partials/flash') ?>
      <?= $content ?>
    </div>
    <?php if (\App\Core\Auth::check()): ?><p class="auth-help"><form method="post" action="<?= e(url('/logout')) ?>" class="inline"><?= csrf_field() ?><button class="link">Sign out</button></form></p><?php endif; ?>
  </section>
</main>
<script src="<?= e(url('/assets/js/app.js?v=' . APP_VERSION)) ?>" defer></script>
<?php foreach (glob(BASE_PATH . '/public/assets/js/modules/*.js') ?: [] as $moduleJs): ?><script src="<?= e(url('/assets/js/modules/' . basename($moduleJs) . '?v=' . APP_VERSION)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
