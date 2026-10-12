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
<link rel="icon" href="<?= e(url('/assets/img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
</head>
<body class="auth">
<div class="auth-bg"></div>
<main class="auth-wrap <?= !empty($wide) ? 'is-wide' : '' ?>">
  <div class="auth-brand">
    <img src="<?= e(url('/assets/img/logo.svg')) ?>" alt="" width="44" height="44">
    <div><strong><?= e(Config::get('app.name')) ?></strong><small><?= e(\App\Models\Setting::get('org_name')) ?></small></div>
  </div>
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
