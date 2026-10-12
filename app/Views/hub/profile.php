<?php use App\Models\Application; $title = 'My Profile'; $active = 'profile'; $idSt = $app['id_verification_status']; $last = $stats['badges'] ? \App\Services\Gamification::BADGES[end($stats['badges'])] : null; ?>
<div class="hero">
  <span class="profile-badge" style="background:<?= e($stats['level_color']) ?>"><?= icon($last ? $last[0] : 'star', '', 30) ?></span>
  <div><h1><?= e($app['name']) ?></h1><p><?= e($app['volunteer_category']) ?> Volunteer · <span class="chip" style="border-color:var(--accent);color:var(--accent);background:transparent">Level <?= $stats['level'] ?> · <?= e($stats['level_title']) ?></span></p></div>
  <span class="grow"></span>
  <div class="hero-stats"><div><b><?= $stats['xp'] ?></b><span>total XP</span></div><div><b><?= rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.') ?></b><span>Hours</span></div><div><b><?= $stats['reports'] ?></b><span>Reports</span></div><div><b><?= $stats['streak'] ?></b><span>Streak</span></div></div>
</div>
<div class="tabs"><a class="tab <?= $tab === 'achievements' ? 'is-active' : '' ?>" href="<?= e(url('/profile')) ?>">Achievements</a><a class="tab <?= $tab === 'edit' ? 'is-active' : '' ?>" href="<?= e(url('/profile?tab=edit')) ?>">Edit Profile</a></div>
<?php if ($tab === 'achievements'): ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card"><?= \App\Core\View::partial('hub/_xp', ['stats' => $stats]) ?></div>
    <div class="card"><?= \App\Core\View::partial('hub/_streak', ['stats' => $stats]) ?></div>
    <div class="card"><div class="card-head"><h2>All Badges</h2><span class="grow"></span><span class="muted small"><?= count($stats['badges']) ?> of <?= count(\App\Services\Gamification::BADGES) ?> badges earned</span></div><?= \App\Core\View::partial('hub/_badges', ['stats' => $stats]) ?></div>
  </div>
  <div class="card-stack">
    <?php if ($idSt === 'approved'): ?><div class="card"><div class="all-clear"><?= icon('check-circle', '', 18) ?> ID Verified — your identity has been confirmed</div></div>
    <?php elseif ($idSt === 'pending'): ?><div class="card"><div class="flash flash-warning" style="margin:0"><strong>ID Under Review.</strong> Documents submitted — awaiting admin approval.</div></div>
    <?php else: ?><a class="card todo <?= $idSt === 'rejected' ? 'is-urgent' : '' ?>" href="<?= e(url('/upload-id')) ?>"><span class="todo-count"><?= icon('shield', '', 22) ?></span><span class="todo-text"><span class="todo-title"><?= $idSt === 'rejected' ? 'Re-upload ID Documents' : 'Upload ID Documents' ?></span><br><span class="todo-sub"><?= $idSt === 'rejected' ? 'Your documents were rejected — please re-submit' : 'Required to complete your volunteer profile' ?></span></span><?= icon('chevron-right', '', 18) ?></a><?php endif; ?>
    <a class="card todo <?= $docs['cv'] ? '' : 'is-warn' ?>" href="<?= e(url('/upload-cv')) ?>"><span class="todo-count"><?= icon('file-text', '', 22) ?></span><span class="todo-text"><span class="todo-title"><?= $docs['cv'] ? 'CV Uploaded' : 'Upload Your CV' ?></span><br><span class="todo-sub"><?= $docs['cv'] ? 'Your CV is on file · update' : 'Helps complete your profile' ?></span></span><?= icon('chevron-right', '', 18) ?></a>
    <div class="card"><h2>Your details</h2><dl class="kv"><dt>Email</dt><dd><?= e($app['email']) ?></dd><dt>Phone</dt><dd><?= e($app['phone'] ?: '—') ?></dd><dt>Location</dt><dd><?= e(trim(($app['city'] ?? '') . ', ' . ($app['country'] ?? ''), ', ') ?: '—') ?></dd><dt>Roles</dt><dd><?= e(implode(', ', $app['role_interests']) ?: '—') ?></dd><dt>Skills</dt><dd><?= e(implode(', ', $app['skills']) ?: '—') ?></dd><dt>Languages</dt><dd><?= e(implode(', ', $app['languages']) ?: '—') ?></dd></dl></div>
    <?php if (!empty($u['teams']) || !empty($u['admin_teams'])): ?><div class="card"><h2>Teams</h2><p class="small muted">You belong to <?= count(array_unique(array_merge($u['teams'], $u['admin_teams']))) ?> team(s). <a href="<?= e(url('/teams/my')) ?>">Open My Teams</a></p></div><?php endif; ?>
  </div>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/profile/connect')) ?>" class="card" style="max-width:820px"><?= csrf_field() ?>
  <div class="grid-2">
    <div class="field"><label>Email Address</label><input value="<?= e($app['email']) ?>" disabled></div>
    <div class="field"><label>Phone / WhatsApp</label><input name="phone" value="<?= e($app['phone']) ?>"></div>
    <div class="field"><label>First Name</label><input name="first_name" value="<?= e($app['first_name']) ?>"></div>
    <div class="field"><label>Last Name</label><input name="last_name" value="<?= e($app['last_name']) ?>"></div>
    <div class="field"><label>Country</label><input name="country" list="countries" value="<?= e($app['country']) ?>"><datalist id="countries"><?php foreach (Application::COUNTRIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
    <div class="field"><label>City</label><input name="city" value="<?= e($app['city']) ?>"></div>
    <?php if (Application::isUk($app)): ?><div class="field span-2"><label>University</label><input name="university" list="universities" value="<?= e($app['university']) ?>"><datalist id="universities"><?php foreach (Application::UK_UNIVERSITIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div><?php endif; ?>
  </div>
  <div class="field"><span class="field-label">Skills</span><div class="choices"><?php foreach (Application::SKILLS as $o): ?><label class="choice"><input type="checkbox" name="skills[]" value="<?= e($o) ?>" <?= in_array($o, $app['skills'], true) ? 'checked' : '' ?>> <?= e($o) ?></label><?php endforeach; ?></div></div>
  <div class="field"><label>Languages</label><input name="languages" value="<?= e(implode(', ', $app['languages'])) ?>" placeholder="English, Arabic, Urdu"><div class="field-hint">Comma-separated</div></div>
  <div class="field"><span class="field-label">Preferred Days</span><div class="choices"><?php foreach (Application::DAYS as $o): ?><label class="choice"><input type="checkbox" name="preferred_days[]" value="<?= e($o) ?>" <?= in_array($o, $app['preferred_days'], true) ? 'checked' : '' ?>> <?= e($o) ?></label><?php endforeach; ?></div></div>
  <div class="field"><span class="field-label">Role Interests</span><div class="choices"><?php foreach (Application::roleOptions($app) as $o): ?><label class="choice"><input type="checkbox" name="role_interests[]" value="<?= e($o) ?>" <?= in_array($o, $app['role_interests'], true) ? 'checked' : '' ?>> <?= e($o) ?></label><?php endforeach; ?></div></div>
  <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/profile?legacy=1')) ?>">Password & two-step</a><button class="btn btn-primary"><?= icon('check', '', 14) ?> Save Changes</button></div>
</form>
<?php endif; ?>
