<?php
use App\Core\Config;
$pageTitle = $title ?? 'Sign in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(Config::get('app.name')) ?></title>
<link rel="icon" href="<?= e(\App\Models\Setting::logoUrl()) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
<?php $__b = \App\Models\Setting::brand(); ?>
<style>:root{--primary:<?= e($__b['primary']) ?>;--primary-600:<?= e($__b['primary_600']) ?>;--primary-50:<?= e($__b['primary_50']) ?>;--accent:<?= e($__b['accent']) ?>;--accent-50:<?= e($__b['accent_50']) ?>;--sidebar:<?= e($__b['dark']) ?>;}</style>
</head>
<body class="auth">
<?php $__bg = (string) \App\Models\Setting::get('login_background', ''); ?>
<div class="auth-bg <?= $__bg ? 'has-image' : '' ?>" <?= $__bg ? 'style="--login-image:url(\'' . e(url($__bg)) . '\')"' : '' ?>></div>
<main class="auth-wrap <?= !empty($wide) ? 'is-wide' : '' ?>">
  <div class="auth-brand">
    <img src="<?= e(\App\Models\Setting::logoUrl()) ?>" alt="" height="56">
    <div><strong><?= e(Config::get('app.name')) ?></strong><small><?= e(\App\Models\Setting::get('org_name')) ?></small></div>
  </div>
  <?php if (\App\Models\Setting::get('login_tagline')): ?><p class="auth-tagline"><?= e(\App\Models\Setting::get('login_tagline')) ?></p><?php endif; ?>
  <div class="card auth-card">
    <?= \App\Core\View::partial('partials/flash') ?>
    <?= $content ?>
  </div>
  <p class="auth-foot">
    <?php if (\App\Core\Auth::check()): ?>
      <form method="post" action="<?= e(url('/logout')) ?>" class="inline"><?= csrf_field() ?><button class="link">Sign out</button></form>
    <?php else: ?>
      Need help? <a href="mailto:<?= e(\App\Models\Setting::get('support_email')) ?>"><?= e(\App\Models\Setting::get('support_email')) ?></a>
    <?php endif; ?>
  </p>
</main>
<script src="<?= e(url('/assets/js/app.js?v=' . APP_VERSION)) ?>" defer></script>
</body>
</html>
