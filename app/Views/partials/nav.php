<?php
use App\Core\Auth;
use App\Models\Setting;
/** @var array|null $me */
$items = [];
if ($me) {
    $super = Auth::isSuperadmin($me);
    $lead = Auth::isLead($me);
    $vol = Auth::hasGroup($me, 'volunteer') || $super;
    $daee = $super || ($vol && Auth::hasSubGroup($me, 'daee')) || $lead;
    $mentor = $super || ($vol && Auth::hasSubGroup($me, 'mentor'));
    $nm = Auth::hasGroup($me, 'newmuslim');
    $onboarded = Auth::isOnboarded($me);
    if ($onboarded) {
        if ($me['type'] === 'superadmin') {
            $items[] = ['Analytics', '/analytics', 'analytics', 'bar-chart'];
            $items[] = ['Admin Dashboard', '/admin/users?type=volunteer', 'admin', 'wrench'];
            $items[] = ['Teams', '/admin/teams', 'admin-teams', 'users'];
        } elseif ($me['type'] === 'org') {
            $items[] = ['New Muslims', '/admin/users?type=newmuslim', 'admin', 'compass'];
        }
        if ($vol && $me['type'] !== 'superadmin' || $super) {
            $items[] = ['Home', '/dashboard', 'dashboard', 'home'];
        }
        if ($vol) {
            $items[] = ['My Teams', '/teams/my', 'teams', 'users'];
        }
        if ($daee) {
            $items[] = ['Street Dawah', null, 'sd', 'megaphone', [
                ['Calendar', '/calendar', 'calendar'],
                ['Event Registration', '/events/register', 'register'],
                ['Pending Event Reports', '/reports/events/pending', 'reports-events-pending'],
                ['Submitted Event Reports', '/reports/events/submitted', 'reports-events-submitted'],
                $lead || $super ? ['Event Planning', '/events/planning', 'planning'] : null,
                $lead || $super ? ['Event Attendance', '/attendance', 'attendance'] : null,
                $lead || $super ? ["Da'ee Activity", '/daee-activity', 'daee'] : null,
            ]];
        }
        if ($mentor || ($lead && !$super)) {
            $items[] = ['New Muslim', null, 'nm', 'heart-handshake', [
                $mentor ? ['Mentees', '/mentees', 'mentees'] : null,
                ['Pending Mentor Reports', '/reports/mentor/pending', 'reports-mentor-pending'],
                ['Submitted Mentor Reports', '/reports/mentor/submitted', 'reports-mentor-submitted'],
                ['Pray With Ease (Mentor)', rtrim((string) Setting::get('docs_base_url'), '/') . '/PWE-Mentor.pdf', 'ext'],
                ['Mentor Handbook', rtrim((string) Setting::get('docs_base_url'), '/') . '/HandBook_Mentor.pdf', 'ext'],
            ]];
        }
        if ($nm) {
            $items[] = ['Home', '/newmuslim/home', 'nm-home', 'home'];
        }
        if ($vol || $lead) {
            $items[] = ['Vacation Requests', '/vacations', 'vacations', 'sun'];
        }
    }
    $items[] = ['My Profile', '/profile', 'profile', 'user'];
    if (Auth::isSysadmin($me)) {
        $items[] = ['System Admin', '/system', 'system', 'settings'];
    }
}
$teamNames = $me ? \App\Models\Team::names() : [];
$myTeams = $me ? array_map(fn($t) => $teamNames[$t] ?? $t, array_unique(array_merge($me['teams'] ?? [], $me['admin_teams'] ?? []))) : [];
?>
<aside class="sidebar" data-sidebar>
  <div class="sidebar-brand">
    <img src="<?= e(url('/assets/img/logo.svg')) ?>" alt="" width="36" height="36">
    <div><strong><?= e(\App\Core\Config::get('app.name')) ?></strong><small><?= e(Setting::get('org_name')) ?></small></div>
    <button class="icon-btn sidebar-close" type="button" aria-label="Close menu" data-nav-toggle><?= icon('x') ?></button>
  </div>
  <?php if ($me): ?>
  <div class="sidebar-user">
    <div class="sidebar-user-name"><?= e(\App\Models\User::fullName($me)) ?></div>
    <div class="sidebar-user-meta"><?= e($myTeams ? implode(', ', $myTeams) : ($me['city'] ?? ucfirst((string) $me['type']))) ?></div>
  </div>
  <?php endif; ?>
  <nav class="sidebar-nav" aria-label="Main">
    <?php foreach ($items as $it): [$label, $href, $key, $icon] = $it; $children = array_values(array_filter($it[4] ?? [])); ?>
      <?php if ($children): $open = str_starts_with($active, $key) || in_array($active, array_column($children, 2), true); ?>
        <details class="nav-group" <?= $open ? 'open' : '' ?>>
          <summary><?= icon($icon, 'nav-icon') ?><?= e($label) ?></summary>
          <?php foreach ($children as [$cl, $ch, $ck]): ?>
            <?php if ($ck === 'ext'): ?>
              <a class="nav-link nav-sub" href="<?= e($ch) ?>" target="_blank" rel="noopener"><?= e($cl) ?> <?= icon('external-link', 'icon-sm', 14) ?></a>
            <?php else: ?>
              <a class="nav-link nav-sub <?= $active === $ck ? 'is-active' : '' ?>" href="<?= e(url($ch)) ?>"><?= e($cl) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </details>
      <?php else: ?>
        <a class="nav-link <?= $active === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"><?= icon($icon, 'nav-icon') ?><?= e($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <?php if ($me && Auth::isOnboarded($me) && !Auth::hasGroup($me, 'newmuslim')): ?>
      <button type="button" class="btn btn-accent btn-block" data-open="referModal"><?= icon('user-plus') ?> Refer a New Muslim</button>
    <?php endif; ?>
    <?php if (Setting::get('feedback_url') && $me && !Auth::hasGroup($me, 'newmuslim')): ?>
      <a class="nav-link" href="<?= e(Setting::get('feedback_url')) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'nav-icon') ?>Report Issues <?= icon('external-link', 'icon-sm', 14) ?></a>
    <?php endif; ?>
    <?php if ($me): ?>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="nav-link nav-button" type="submit"><?= icon('log-out', 'nav-icon') ?>Sign out</button></form>
    <?php endif; ?>
  </div>
</aside>
<div class="sidebar-backdrop" data-nav-toggle></div>
