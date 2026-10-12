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
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?> · <?= e(Config::get('app.name')) ?></title>
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<link rel="icon" href="<?= e(\App\Models\Setting::logoUrl()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
<?php $__b = \App\Models\Setting::brand(); ?>
<style>:root{--primary:<?= e($__b['primary']) ?>;--primary-600:<?= e($__b['primary_600']) ?>;--primary-50:<?= e($__b['primary_50']) ?>;--accent:<?= e($__b['accent']) ?>;--accent-50:<?= e($__b['accent_50']) ?>;--sidebar:<?= e($__b['dark']) ?>;}</style>
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
      <button class="icon-btn menu-toggle" type="button" aria-label="Open menu" data-nav-toggle><?= icon('menu', '', 24) ?></button>
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
        <?php $__unread = $me ? \App\Models\Notification::unreadCount($me['id']) : 0; ?>
        <div class="bell-wrap" data-bell>
          <a class="icon-btn bell" href="<?= e(url('/notifications')) ?>" aria-label="Notifications" data-bell-toggle><?= icon('bell', '', 22) ?><?php if ($__unread): ?><span class="bell-count"><?= $__unread > 99 ? '99+' : $__unread ?></span><?php endif; ?></a>
          <div class="bell-panel" hidden data-bell-panel><div class="bell-head"><strong>Notifications</strong><a class="small" href="<?= e(url('/notifications')) ?>">See all</a></div><div class="bell-list" data-bell-list><div class="muted small" style="padding:12px">Loading…</div></div></div>
        </div>
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
<?php if ($me && ($me['type'] === 'volunteer' || Auth::isAdmin($me)) && \App\Models\Application::forUser($me['id']) || ($me && Auth::isAdmin($me))): ?><?= \App\Core\View::partial('partials/feedback_button') ?><?php endif; ?>
<script src="<?= e(url('/assets/js/app.js?v=' . APP_VERSION)) ?>" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
