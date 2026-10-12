<?php
use App\Core\Auth;
use App\Models\Setting;
/** @var array|null $me */
$items = [];
$role = '';
if ($me) {
    $super = Auth::isSuperadmin($me);
    $lead = Auth::isLead($me);
    $vol = Auth::hasGroup($me, 'volunteer') || $super;
    $daee = $super || ($vol && Auth::hasSubGroup($me, 'daee')) || $lead;
    $mentor = $super || ($vol && Auth::hasSubGroup($me, 'mentor'));
    $nm = Auth::hasGroup($me, 'newmuslim');
    $onboarded = Auth::isOnboarded($me);
    $docs = rtrim((string) Setting::get('docs_base_url'), '/');
    // A short role label shown under the user's name so people know what they can do here.
    $role = match (true) {
        $super => Auth::isSysadmin($me) ? 'Super admin' : 'Administrator',
        $me['type'] === 'org' => 'Organization',
        $nm => 'New Muslim',
        $lead => 'Team lead',
        $me['type'] === 'volunteer' && ($app0 = \App\Models\Application::forUser($me['id'])) => $app0['volunteer_category'] . ' volunteer · ' . ($app0['status'] === 'Approved' ? 'Approved' : ($app0['submitted_at'] ? 'Application under review' : 'Profile incomplete')),
        default => implode(' · ', array_filter([Auth::hasSubGroup($me, 'daee') ? "Da'ee" : null, Auth::hasSubGroup($me, 'mentor') ? 'Mentor' : null])) ?: ($vol ? 'Volunteer' : 'Registration in progress'),
    };
    $application = $me['type'] === 'volunteer' ? \App\Models\Application::forUser($me['id']) : null;
    // Each item: [label, href, key, icon, section, children?]. Sections appear in this order: Overview, Programs, People, System, Account.
    if ($application) {
        $complete = \App\Models\Application::agreementComplete($application);
        $items[] = ['Home', '/dashboard', 'dashboard', 'home', 'Overview'];
        if (!$complete) {
            $items[] = ['Complete your profile', '/onboarding', 'onboarding', 'clipboard', 'Overview'];
        }
        $items[] = ['Training', '/training', 'training', 'graduation-cap', 'Overview'];
        if (\App\Models\Application::isUk($application) && $complete) {
            $items[] = ['Book your interview', '/book-interview', 'interview', 'calendar', 'Overview'];
        }
        $items[] = ['Notifications', '/notifications', 'notifications', 'bell', 'Account'];
    }
    if ($onboarded) {
        if ($super) {
            $items[] = ['Dashboard', '/dashboard', 'dashboard', 'layout', 'Overview'];
            $items[] = ['Analytics', '/analytics', 'analytics', 'bar-chart', 'Overview'];
        } elseif ($me['type'] === 'org') {
            $items[] = ['New Muslims you referred', '/admin/users?type=newmuslim', 'admin', 'compass', 'Overview'];
        } elseif ($nm) {
            $items[] = ['Home', '/newmuslim/home', 'nm-home', 'home', 'Overview'];
        } elseif (!$application) {
            $items[] = ['Home', '/dashboard', 'dashboard', 'home', 'Overview'];
        }
        if ($vol && !$super) {
            $items[] = ['My Teams', '/teams/my', 'teams', 'users', 'Overview'];
        }
        if ($daee) {
            $items[] = ['Street Dawah', null, 'sd', 'megaphone', 'Programs', [
                ['Calendar', '/calendar', 'calendar'],
                ['Register for an event', '/events/register', 'register'],
                ['Event reports to submit', '/reports/events/pending', 'reports-events-pending'],
                ['Submitted event reports', '/reports/events/submitted', 'reports-events-submitted'],
                $lead || $super ? ['Event planning', '/events/planning', 'planning'] : null,
                $lead || $super ? ['Event attendance', '/attendance', 'attendance'] : null,
                $lead || $super ? ["Da'ee activity", '/daee-activity', 'daee'] : null,
            ]];
        }
        if ($mentor || ($lead && !$super)) {
            $items[] = ['New Muslim Care', null, 'nm', 'heart-handshake', 'Programs', [
                $mentor ? ['My mentees', '/mentees', 'mentees'] : null,
                ['Mentor reports to submit', '/reports/mentor/pending', 'reports-mentor-pending'],
                ['Submitted mentor reports', '/reports/mentor/submitted', 'reports-mentor-submitted'],
                ['Pray With Ease (mentor guide)', $docs . '/PWE-Mentor.pdf', 'ext'],
                ['Mentor handbook', $docs . '/HandBook_Mentor.pdf', 'ext'],
            ]];
        }
        if ($super) {
            $items[] = ['People', null, 'admin', 'users', 'People', [
                ['All users', '/admin/users?type=all', 'admin-all'],
                ['Volunteers', '/admin/users?type=volunteer', 'admin-volunteer'],
                ['New Muslims', '/admin/users?type=newmuslim', 'admin-newmuslim'],
                ['Organizations', '/admin/users?type=org', 'admin-org'],
                ['Unfinished registrations', '/admin/users?type=pending', 'admin-pending'],
            ]];
            $items[] = ['Teams', '/admin/teams', 'admin-teams', 'map-pin', 'People'];
        }
        if ($vol || $lead) {
            $items[] = ['Vacation requests', '/vacations', 'vacations', 'sun', 'Account'];
        }
    }
    $items[] = ['My profile', '/profile', 'profile', 'user', 'Account'];
    if (Auth::isSysadmin($me)) {
        $items[] = ['System admin', '/system', 'system', 'settings', 'System'];
    }
}
$teamNames = $me ? \App\Models\Team::names() : [];
$myTeams = $me ? array_map(fn($t) => $teamNames[$t] ?? $t, array_unique(array_merge($me['teams'] ?? [], $me['admin_teams'] ?? []))) : [];
$rank = ['Overview' => 0, 'Programs' => 1, 'People' => 2, 'System' => 3, 'Account' => 4];
usort($items, fn($a, $b) => $rank[$a[4]] <=> $rank[$b[4]]);
$active = $active ?? '';
$activeList = $active === 'admin' ? 'admin-' . ($_GET['type'] ?? 'volunteer') : $active;
?>
<aside class="sidebar" data-sidebar>
  <div class="sidebar-brand">
    <img src="<?= e(Setting::logoUrl()) ?>" alt="" height="38">
    <div><strong><?= e(\App\Core\Config::get('app.name')) ?></strong><small><?= e(Setting::get('org_name')) ?></small></div>
    <button class="icon-btn sidebar-close" type="button" aria-label="Close menu" data-nav-toggle><?= icon('x') ?></button>
  </div>
  <?php if ($me): ?>
  <a class="sidebar-user" href="<?= e(url('/profile')) ?>">
    <span class="avatar"><?= e(mb_strtoupper(mb_substr($me['first_name'] ?: ($me['email'] ?? '?'), 0, 1))) ?></span>
    <span class="sidebar-user-text">
      <span class="sidebar-user-name"><?= e(\App\Models\User::fullName($me)) ?></span>
      <span class="sidebar-user-meta"><?= e($myTeams ? implode(', ', $myTeams) : ($me['city'] ?: $me['email'])) ?></span>
      <span class="sidebar-role"><?= e($role) ?></span>
    </span>
  </a>
  <?php endif; ?>
  <nav class="sidebar-nav" aria-label="Main">
    <?php $section = null; foreach ($items as $it): [$label, $href, $key, $icon, $sec] = $it; $children = array_values(array_filter($it[5] ?? []));
      if ($sec !== $section): $section = $sec; ?><div class="nav-section"><?= e($section) ?></div><?php endif; ?>
      <?php if ($children): $childKeys = array_column($children, 2); $open = str_starts_with($active, $key) || in_array($active, $childKeys, true) || in_array($activeList, $childKeys, true); ?>
        <details class="nav-group" <?= $open ? 'open' : '' ?>>
          <summary><?= icon($icon, 'nav-icon') ?><?= e($label) ?><?= icon('chevron-down', 'nav-caret', 16) ?></summary>
          <div class="nav-children">
          <?php foreach ($children as [$cl, $ch, $ck]): ?>
            <?php if ($ck === 'ext'): ?>
              <a class="nav-link nav-sub" href="<?= e($ch) ?>" target="_blank" rel="noopener"><?= e($cl) ?><?= icon('external-link', 'icon-sm', 13) ?></a>
            <?php else: ?>
              <a class="nav-link nav-sub <?= ($active === $ck || $activeList === $ck) ? 'is-active' : '' ?>" href="<?= e(url($ch)) ?>"><?= e($cl) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          </div>
        </details>
      <?php else: ?>
        <a class="nav-link <?= $active === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"><?= icon($icon, 'nav-icon') ?><?= e($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <?php if ($me && Auth::isOnboarded($me) && !Auth::hasGroup($me, 'newmuslim')): ?>
      <button type="button" class="btn btn-accent btn-block" data-open="referModal"><?= icon('user-plus', '', 16) ?> Refer a New Muslim</button>
    <?php endif; ?>
    <?php if (Setting::get('feedback_url') && $me && !Auth::hasGroup($me, 'newmuslim')): ?>
      <a class="nav-link" href="<?= e(Setting::get('feedback_url')) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'nav-icon') ?>Report an issue<?= icon('external-link', 'icon-sm', 13) ?></a>
    <?php endif; ?>
    <?php if ($me): ?>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="nav-link nav-button" type="submit"><?= icon('log-out', 'nav-icon') ?>Sign out</button></form>
    <?php endif; ?>
    <div class="sidebar-version"><?= e(\App\Core\Config::get('app.name')) ?> v<?= e(APP_VERSION) ?></div>
  </div>
</aside>
<div class="sidebar-backdrop" data-nav-toggle></div>
