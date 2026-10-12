<?php
use App\Core\Config;
use App\Models\Setting;
$me = \App\Core\Auth::user();
$pageTitle = $title ?? 'Onboarding';
$__b = Setting::brand();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?> · <?= e(Config::get('app.name')) ?></title>
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<link rel="icon" href="<?= e(Setting::logoUrl()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
<style>:root{--primary:<?= e($__b['primary']) ?>;--primary-600:<?= e($__b['primary_600']) ?>;--primary-50:<?= e($__b['primary_50']) ?>;--accent:<?= e($__b['accent']) ?>;--accent-50:<?= e($__b['accent_50']) ?>;--sidebar:<?= e($__b['dark']) ?>;}</style>
</head>
<body class="onboarding">
<header class="ob-top">
  <a class="ob-brand" href="<?= e(url('/')) ?>"><img src="<?= e(Setting::logoUrl()) ?>" alt="" height="34"><span><?= e(Config::get('app.name')) ?> <small>Volunteers</small></span></a>
  <span class="grow"></span>
  <?php if ($me): ?><span class="ob-user small muted"><?= e($me['email']) ?></span><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= icon('log-out', '', 14) ?> Sign out</button></form><?php endif; ?>
</header>
<main class="ob-main">
  <?= \App\Core\View::partial('partials/flash') ?>
  <?= $content ?>
</main>
<footer class="ob-foot">&copy; <?= date('Y') ?> <?= e(Setting::get('org_name')) ?> · <?= e(Config::get('app.name')) ?></footer>
<script src="<?= e(url('/assets/js/app.js?v=' . APP_VERSION)) ?>" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
