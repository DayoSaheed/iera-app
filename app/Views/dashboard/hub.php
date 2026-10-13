<?php
use App\Models\Application;
$title = 'Home'; $active = 'dashboard';
$uk = Application::isCanada($app); $online = Application::isOnline($app); $idSt = $app['id_verification_status'];
$lock = fn(string $label) => '<div class="blur-gate"><div class="blur-gate-msg">' . icon('lock', '', 18) . ' ' . e($label) . '</div></div>';
$status = $approved ? 'Approved' : ($app['submitted_at'] ? Application::displayStatus($app) : 'Application not submitted');
?>
<div class="hero">
  <div><div class="eyebrow">Welcome back</div><h1>Assalamu alaikum, <?= e($app['first_name'] ?: $u['first_name'] ?: 'there') ?></h1>
    <p><span class="chip" style="border-color:rgba(255,255,255,.4);color:#fff;background:transparent"><?= e($app['volunteer_category']) ?> volunteer</span> <span class="badge"><?= e($status) ?></span> <span class="chip" style="border-color:rgba(255,255,255,.4);color:#fff;background:transparent">Level <?= $stats['level'] ?> · <?= e($stats['level_title']) ?></span><?php if ($stats['streak']): ?> <span class="chip" style="border-color:var(--primary);color:#fff;background:var(--primary)"><?= $stats['streak'] ?>w streak</span><?php endif; ?></p>
    <div class="progress-bar" style="max-width:360px;background:rgba(255,255,255,.15);border-color:transparent"><span style="width:<?= $stats['progress'] ?>%"></span></div></div>
  <span class="grow"></span>
  <div class="hero-stats"><div><b><?= $stats['xp'] ?></b><span>XP</span></div><?php if ($unreadDm): ?><a href="<?= e(url('/messages')) ?>" style="color:#fff;text-decoration:none"><b><?= $unreadDm ?></b><span>new messages</span></a><?php endif; ?></div>
  <a class="btn btn-accent" href="<?= e(url('/training')) ?>"><?= icon('graduation-cap', '', 16) ?> Training</a>
  <a class="btn btn-light" href="<?= e(url('/profile')) ?>"><?= icon('user', '', 16) ?> My profile</a>
</div>

<?php if ($approved && $idSt === 'approved'): ?>
<?php elseif ($idSt === 'approved'): ?>
<div class="flash flash-success"><strong>ID Verified.</strong> Your identity has been confirmed. Your application is now under review — we'll notify you when approved.</div>
<?php elseif ($idSt === 'pending' || (!empty($app['id_doc_1_id']) && !empty($app['id_doc_2_id']))): ?>
<div class="flash flash-warning"><strong>ID Documents Submitted — Under Review.</strong> Your documents are with our team. We'll notify you once verified, usually within 1–2 working days.</div>
<?php elseif ($idSt === 'rejected'): ?>
<div class="flash flash-error"><strong>ID Documents Rejected.</strong> <?= e($app['id_rejection_reason'] ?: 'Your documents could not be verified. Please re-upload clear, valid ID documents.') ?> <a class="btn btn-sm btn-dark" href="<?= e(url('/upload-id')) ?>">Re-upload ID Documents</a></div>
<?php else: ?>
<div class="flash flash-warning" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><div class="grow"><strong>Action Required: Submit Your ID.</strong> To complete your volunteer verification, you must submit <strong>2 forms of ID</strong>. This is required before your application can be reviewed and approved.<br><small><?= icon(!empty($app['id_doc_1_id']) ? 'check-circle' : 'x-circle', '', 12) ?> ID Document 1 &nbsp; <?= icon(!empty($app['id_doc_2_id']) ? 'check-circle' : 'x-circle', '', 12) ?> ID Document 2</small></div><a class="btn btn-dark" href="<?= e(url('/upload-id')) ?>">Upload ID <?= icon('chevron-right', '', 14) ?></a></div>
<?php endif; ?>

<section class="section" style="margin-top:0">
  <div class="section-head"><div class="section-title"><span class="section-num">1</span><div><h2>Your onboarding</h2><p class="section-sub"><?= $pct ?>% complete. <?= $pct === 0 ? "Let's get started!" : ($pct < 50 ? 'Great start — keep going!' : ($pct < 100 ? 'Almost there!' : 'All done!')) ?></p></div></div></div>
  <div class="two-col">
    <div class="card">
      <div class="progress-bar"><span style="width:<?= $pct ?>%"></span></div>
      <ul class="check-list" style="margin-top:12px">
        <?php foreach ($checks as [$label, $done, $href, $pending]): ?>
        <li><span><?= icon($done ? 'check-circle' : 'minus', '', 16) ?> <?= $done ? '<s class="muted">' . e($label) . '</s>' : e($label) ?><?= !$done && $pending ? ' <span class="badge badge-pending">' . e($pending) . '</span>' : '' ?></span><?php if (!$done && $href): ?><a class="btn btn-sm btn-ghost" href="<?= e(url($href)) ?>">Go</a><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="card-stack">
      <?php if ($uk && empty($app['call_scheduled'])): ?>
      <div class="card"><h2><?= icon('calendar', '', 16) ?> Book Your Interview</h2><p class="muted">You can now book a time for your onboarding interview with the iERA team.</p><a class="btn btn-primary" href="<?= e(url('/book-interview')) ?>">Book a time <?= icon('chevron-right', '', 14) ?></a></div>
      <?php elseif ($uk && $booked && $app['interview_status'] === 'scheduled'): ?>
      <div class="card"><h2><?= icon('calendar', '', 16) ?> Your interview</h2><p><strong><?= e(fmt_date($booked['slot_date'], 'l, j F Y')) ?></strong> at <?= e($booked['slot_time']) ?>. A member of the onboarding team will phone you then.</p></div>
      <?php elseif (!$uk && !empty($app['cv_doc_id']) && in_array($idSt, ['pending', 'approved'], true) && empty($app['call_scheduled'])): ?>
      <div class="card"><h2><?= icon('calendar', '', 16) ?> Onboarding Interview</h2><p class="muted">Once your documents are reviewed, you'll be invited to the onboarding interview to complete your onboarding.</p></div>
      <?php endif; ?>
      <?php if ($online): ?>
      <div class="card"><h2><?= icon('target', '', 16) ?> Online Action & Training Center</h2><p class="muted">See exactly where you are in your Online onboarding and what to do next.</p><a class="btn btn-dark" href="<?= e(url('/online-action-center')) ?>">Open <?= icon('chevron-right', '', 14) ?></a></div>
      <?php endif; ?>
      <div class="card"><h2><?= icon('globe', '', 16) ?> <?= !empty($app['online_dawah_interest']) ? 'Online Dawah Interest Registered' : 'Join the Online Dawah Team' ?></h2>
        <?php if (!empty($app['online_dawah_interest'])): ?><p class="muted">Jazak Allahu khair for your interest! Our team will be in touch with you soon, in shaa Allah.</p>
        <?php else: ?><p class="muted">Want to share the message of Islam online? Register your interest and our team will follow up with you.</p><form method="post" action="<?= e(url('/online-interest')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">I'm interested</button></form><?php endif; ?></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">2</span><div><h2>Your volunteer hub</h2><p class="section-sub"><?= $approved ? 'Everything is unlocked. JazakAllah khair for joining.' : 'Training is open now. The rest unlocks once your application is approved.' ?></p></div></div></div>
  <div class="todo-list">
    <a class="todo" href="<?= e(url('/training')) ?>"><span class="todo-count"><?= icon('graduation-cap', '', 22) ?></span><span class="todo-text"><span class="todo-title">Training</span><br><span class="todo-sub">Dawah course, role training and certificates</span></span><?= icon('chevron-right', '', 18) ?></a>
    <?php foreach ([['Community Feed', 'Post updates & connect', '/community', 'message-square'], ['Member Directory', 'Find & message volunteers', '/members', 'users'], ['Log Impact', 'Submit activity reports · +20 XP', '/impact', 'clipboard'], ['Events', 'Upcoming dawah events', '/events', 'calendar'], ['Leaderboard', 'See your ranking', '/leaderboard', 'trophy'], ['Stories', 'Read and share dawah stories', '/stories', 'book']] as [$t, $sub, $href, $ico]): $locked = !$approved && $href !== '/stories'; ?>
    <a class="todo <?= $locked ? 'is-zero' : '' ?>" href="<?= $locked ? '#' : e(url($href)) ?>" <?= $locked ? 'title="Available after approval" aria-disabled="true"' : '' ?>><span class="todo-count"><?= icon($locked ? 'lock' : $ico, '', 22) ?></span><span class="todo-text"><span class="todo-title"><?= e($t) ?></span><br><span class="todo-sub"><?= $locked ? 'Available after approval' : e($sub) ?></span></span><?= icon('chevron-right', '', 18) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">3</span><div><h2>Your stats and badges</h2><p class="section-sub"><?= $approved ? 'Earn XP by logging impact, completing training and sharing stories.' : 'Your stats unlock once your application is approved.' ?></p></div></div><?php if ($approved): ?><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/impact?new=1')) ?>"><?= icon('plus', '', 14) ?> Log impact</a><?php endif; ?></div>
  <div class="<?= $approved ? '' : 'blur-gate' ?>">
    <div class="stat-row">
      <div class="stat accent"><div class="stat-label">Total XP</div><div class="stat-value"><?= $stats['xp'] ?></div><div class="stat-sub"><?= $stats['to_next'] ? $stats['to_next'] . ' XP to next level' : 'Max level' ?></div></div>
      <div class="stat plain"><div class="stat-label">Hours</div><div class="stat-value"><?= rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.') ?></div></div>
      <div class="stat plain"><div class="stat-label">Reports</div><div class="stat-value"><?= $stats['reports'] ?></div></div>
      <div class="stat primary"><div class="stat-label">Badges</div><div class="stat-value"><?= count($stats['badges']) ?></div><div class="stat-sub"><a href="<?= e(url('/profile')) ?>">View all badges</a></div></div>
    </div>
    <?php if ($stats['badges']): ?><div class="card" style="margin-top:14px"><div class="card-head"><h2>Recent badges</h2></div><?= \App\Core\View::partial('hub/_badges', ['stats' => ['badges' => array_slice($stats['badges'], -6)], 'showLocked' => false]) ?></div><?php endif; ?>
    <?php if (!$approved): ?><div class="blur-gate-msg"><?= icon('lock', '', 18) ?> Your stats unlock once your application is approved.</div><?php endif; ?>
  </div>
</section>
<?php if ($approved && ($recentPosts || $recentReports || $communityEvents)): ?>
<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">4</span><div><h2>Community and activity</h2><p class="section-sub">The latest from the feed and your recent reports.</p></div></div></div>
  <div class="two-col">
    <div class="card"><div class="card-head"><h2>Community Feed</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/community')) ?>">See all</a></div>
      <?php if (!$recentPosts): ?><p class="muted">Be the first to post!</p><?php else: ?><ul class="check-list"><?php foreach ($recentPosts as $p): ?><li><span><strong><?= e($p['author_name']) ?></strong> <span class="muted"><?= e(mb_substr((string) $p['content'], 0, 90)) ?></span></span></li><?php endforeach; ?></ul><?php endif; ?></div>
    <div class="card-stack">
      <?php if ($recentReports): ?><div class="card"><div class="card-head"><h2>Recent Activity</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/impact')) ?>">See all</a></div><ul class="check-list"><?php foreach ($recentReports as $r): ?><li><span><?= e($r['activity_type']) ?> <small class="muted"><?= e(fmt_date($r['report_date'])) ?> · <?= rtrim(rtrim(number_format((float) $r['hours'], 1), '0'), '.') ?>h</small></span><span class="pill pill-accent">+20 XP</span></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if ($communityEvents): ?><div class="card"><div class="card-head"><h2>Events</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/events')) ?>">See all</a></div><ul class="check-list"><?php foreach ($communityEvents as $ev): ?><li><span><?= e($ev['title']) ?></span><small class="muted"><?= e(fmt_date($ev['event_date'])) ?></small></li><?php endforeach; ?></ul></div><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php if ($approved && ($upcoming || $pendingReports || $teams)): ?>
<section class="section">
  <div class="section-head"><div class="section-title"><span class="section-num">5</span><div><h2>Your teams and events</h2><p class="section-sub">Events for the teams you belong to and reports waiting for you.</p></div></div></div>
  <div class="two-col">
    <div class="card"><div class="card-head"><h2>Upcoming events</h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/calendar')) ?>">Calendar</a></div>
      <?php if (!$upcoming): ?><p class="muted">No events scheduled for your teams in the next 30 days.</p><?php else: ?><table class="table"><thead><tr><th>When</th><th>Event</th><th>Team</th></tr></thead><tbody><?php foreach ($upcoming as $s): ?><tr><td class="muted"><?= e(fmt_datetime($s['start_at'])) ?></td><td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['team_name']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
    <div class="card"><div class="card-head"><h2>My teams</h2></div>
      <?php if (!$teams): ?><p class="muted">You are not in a team yet. A team lead will add you after approval.</p><?php else: ?><ul class="check-list"><?php foreach ($teams as $t): ?><li><a href="<?= e(url('/teams/my/' . $t['id'])) ?>"><strong><?= e($t['name']) ?></strong></a></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($pendingReports): ?><p class="small" style="margin-top:10px"><a href="<?= e(url('/reports/events/pending')) ?>"><?= count($pendingReports) ?> report(s) waiting for you</a></p><?php endif; ?></div>
  </div>
</section>
<?php endif; ?>
