<?php
use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Models\Setting;
$me = Auth::user();
$pageTitle = $title ?? 'Dashboard';
$impersonator = $_SESSION['impersonator'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(Config::get('app.name')) ?></title>
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<link rel="icon" href="<?= e(url('/assets/img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
</head>
<body class="app">
<a class="skip" href="#main">Skip to content</a>
<?php if ($impersonator): ?>
<div class="impersonation-bar">Viewing as <strong><?= e(\App\Models\User::fullName($me)) ?></strong>
  <form method="post" action="<?= e(url('/system/stop-impersonating')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-light">Return to my account</button></form>
</div>
<?php endif; ?>
<?php if (Setting::bool('maintenance_mode')): ?>
<div class="maintenance-bar">Maintenance mode is on: only system admins can use the app.</div>
<?php endif; ?>
<div class="shell">
  <?= \App\Core\View::partial('partials/nav', ['me' => $me, 'active' => $active ?? '']) ?>
  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn menu-toggle" type="button" aria-label="Open menu" data-nav-toggle>☰</button>
      <div class="topbar-title">
        <?php if (!empty($breadcrumbs)): ?>
          <nav class="crumbs" aria-label="Breadcrumb">
            <?php foreach ($breadcrumbs as $label => $href): ?>
              <?php if ($href): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><span class="sep">/</span><?php else: ?><span><?= e($label) ?></span><?php endif; ?>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
        <h1><?= e($pageTitle) ?></h1>
      </div>
      <div class="topbar-actions">
        <?= $actions ?? '' ?>
        <a class="user-chip" href="<?= e(url('/profile')) ?>" title="<?= e($me['email'] ?? '') ?>">
          <span class="avatar"><?= e(mb_strtoupper(mb_substr($me['first_name'] ?? $me['email'] ?? '?', 0, 1))) ?></span>
          <span class="user-name"><?= e(\App\Models\User::fullName($me)) ?></span>
        </a>
      </div>
    </header>
    <main id="main" class="content">
      <?= \App\Core\View::partial('partials/flash') ?>
      <?= $content ?>
    </main>
    <footer class="footer">
      <?= e(Setting::get('org_name')) ?> · <?= e(Config::get('app.name')) ?> v<?= e(APP_VERSION) ?>
      <?php if (Setting::get('support_email')): ?> · <a href="mailto:<?= e(Setting::get('support_email')) ?>">Contact support</a><?php endif; ?>
    </footer>
  </div>
</div>
<?php if (!empty($_SESSION['ask_new_muslim'])): $n = (int) $_SESSION['ask_new_muslim']; unset($_SESSION['ask_new_muslim']); ?>
<dialog class="modal" id="askNewMuslim" data-autoopen>
  <form method="dialog" class="modal-body">
    <h2>Alhamdulillah, <?= $n ?> shahada<?= $n > 1 ? 's' : '' ?>!</h2>
    <p>Did you add the New Muslim(s) so a mentor can be assigned?</p>
    <div class="modal-actions">
      <button class="btn btn-ghost" value="yes">Yes, already added</button>
      <button class="btn btn-primary" type="button" data-open="referModal" data-close="askNewMuslim">Add New Muslim now</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<?= \App\Core\View::partial('partials/refer_modal') ?>
<script src="<?= e(url('/assets/js/app.js?v=' . APP_VERSION)) ?>" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
