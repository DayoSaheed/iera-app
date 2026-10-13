<?php
use App\Core\Auth;
use App\Models\Application;
$title = 'Review · ' . ($app['name'] ?: $app['email']);
$active = 'cc-apps-all';
$super = Auth::isSuperadmin($u);
$sys = Auth::isSysadmin($u);
$isCanada = Application::isCanada($app);
$d = Application::displayStatus($app);
$badge = fn(string $x) => ['New' => 'new', 'Under Review' => 'in_review', 'Approved' => 'approved', 'Onboarded' => 'completed', 'Waitlisted' => 'paused', 'Rejected' => 'rejected', 'Inactive' => 'inactive', 'pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected'][$x] ?? 'default';
$self = '/admin/applications/' . $app['id'];
$act = url($self . '/action');
$tabUrl = fn(string $t) => url($self . '?tab=' . $t);
$yn = fn($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
$list = fn(array $v) => $v ? e(implode(', ', $v)) : '—';
$docLink = fn(?array $doc) => $doc ? '<a href="' . e(url('/documents/' . $doc['id'])) . '" target="_blank">' . e($doc['filename']) . '</a> <span class="small muted">' . e(fmt_datetime($doc['created_at'])) . '</span>' : '<span class="muted">Not uploaded</span>';
$canadaRoles = Application::canadaRoles($app);
$certKeys = array_values(array_unique(array_filter(array_merge(array_map(fn($r) => Application::CA_ROLE_CERT[$r] ?? null, $canadaRoles), array_keys($app['certs'])))));
?>
<div class="hero">
  <div>
    <span class="eyebrow"><?= e($app['volunteer_category']) ?> volunteer · #<?= e(strtoupper(substr($app['id'], -6))) ?></span>
    <h1><?= e($app['name'] ?: '(no name)') ?></h1>
    <p><?= e($app['email']) ?><?= $app['phone'] ? ' · ' . e($app['phone']) : '' ?><?= $app['city'] || $app['country'] ? ' · ' . e(trim(($app['city'] ?? '') . ', ' . ($app['country'] ?? ''), ', ')) : '' ?> · applied <?= e(fmt_date($app['created_at'])) ?></p>
  </div>
  <span class="grow"></span>
  <div class="actions">
    <span class="badge badge-<?= e($badge($d)) ?>" style="font-size:.9rem;padding:6px 12px"><?= e($d) ?></span>
    <?php if (!empty($app['is_inactive'])): ?><span class="badge badge-inactive">No longer volunteering</span><?php endif; ?>
    <?php if ($super && $target): ?><a class="btn btn-light btn-sm" href="<?= e(url('/admin/users/' . $app['user_id'])) ?>"><?= icon('user', '', 14) ?> User account</a><?php endif; ?>
    <a class="btn btn-light btn-sm" href="<?= e(url('/admin/applications')) ?>"><?= icon('chevron-left', '', 14) ?> All applications</a>
  </div>
</div>

<?php if ($issues): ?><div class="flash flash-warning"><strong>Data quality flags:</strong> <?= e(implode(' · ', $issues)) ?></div><?php endif; ?>
<?php if ($blockers && $app['status'] !== 'Approved'): ?><div class="flash flash-error"><strong>Approval is blocked</strong> until: <?= e(implode('; ', $blockers)) ?></div><?php endif; ?>

<div class="card">
  <div class="card-head"><h2>Decisions</h2><span class="grow"></span><span class="small muted">Every change is noted on the application and audited.</span></div>
  <div class="grid-3">
    <form method="post" action="<?= $act ?>" class="field" data-confirm="Change the application status?"><?= csrf_field() ?><input type="hidden" name="action" value="status">
      <label>Application status</label>
      <div class="actions"><select name="status"><?php foreach (Application::STATUSES as $st): ?><option <?= $app['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Update</button></div>
      <div class="field-hint">Approved sends the welcome email and unlocks the volunteer hub.</div>
    </form>
    <form method="post" action="<?= $act ?>" class="field"><?= csrf_field() ?><input type="hidden" name="action" value="reviewer">
      <label>Assigned reviewer (email)</label>
      <div class="actions"><input name="reviewer" value="<?= e($app['reviewer']) ?>" placeholder="admin@example.org" class="input"><button class="btn btn-sm">Save</button></div>
      <div class="field-hint">The reviewer gets an in-app notification.</div>
    </form>
    <form method="post" action="<?= $act ?>" class="field"><?= csrf_field() ?><input type="hidden" name="action" value="tags">
      <label>Tags (comma separated)</label>
      <div class="actions"><input name="tags" value="<?= e(implode(', ', $app['tags'])) ?>" placeholder="priority, student, arabic" class="input"><button class="btn btn-sm">Save</button></div>
    </form>
  </div>
  <div class="actions" style="margin-top:4px">
    <?php if ($isCanada): ?>
      <?php if (empty($app['onboarded_approved'])): ?>
        <form method="post" action="<?= $act ?>" data-confirm="Approve this volunteer as fully onboarded? They move to the Onboarded list."><?= csrf_field() ?><input type="hidden" name="action" value="onboarded"><input type="hidden" name="value" value="1"><button class="btn btn-sm btn-accent" <?= ($gateBlocked || $raPending) ? 'disabled title="Complete the risk assessment and approve every applied role first"' : '' ?>><?= icon('user-check', '', 14) ?> Approve as onboarded</button></form>
        <?php if ($gateBlocked): ?><span class="small muted">Approve each applied role below first.</span><?php elseif ($raPending): ?><span class="small muted">Risk assessment still pending.</span><?php endif; ?>
      <?php else: ?>
        <form method="post" action="<?= $act ?>" data-confirm="Move this volunteer back to Applications?"><?= csrf_field() ?><input type="hidden" name="action" value="onboarded"><input type="hidden" name="value" value="0"><button class="btn btn-sm btn-ghost">Move back to applications</button></form>
        <span class="small muted">Onboarded <?= e(fmt_date($app['onboarded_date'])) ?> by <?= e($app['onboarded_approved_by'] ?: '—') ?></span>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?= $act ?>" data-confirm="<?= empty($app['is_inactive']) ? 'Mark this volunteer as no longer volunteering?' : 'Re-activate this volunteer?' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="inactive"><input type="hidden" name="value" value="<?= empty($app['is_inactive']) ? '1' : '0' ?>"><button class="btn btn-sm btn-ghost"><?= empty($app['is_inactive']) ? 'No longer volunteering' : 'Re-activate volunteer' ?></button></form>
    <?php if ($sys): ?><form method="post" action="<?= $act ?>" data-confirm="Sign in as this volunteer? Your admin session is kept and you can return from the top bar."><?= csrf_field() ?><input type="hidden" name="action" value="impersonate"><button class="btn btn-sm btn-ghost"><?= icon('eye', '', 14) ?> View as volunteer</button></form><?php endif; ?>
    <details style="margin-left:auto"><summary class="btn btn-sm btn-ghost" style="list-style:none">Change category</summary>
      <form method="post" action="<?= $act ?>" class="actions" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="category">
        <select name="category"><?php foreach (Application::CATEGORIES as $k => $v): ?><option value="<?= e($k) ?>" <?= $app['volunteer_category'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <input class="input" name="reason" placeholder="Reason (required)" style="max-width:260px" required><button class="btn btn-sm">Change</button>
      </form>
    </details>
  </div>
</div>

<div class="tabs">
  <a class="tab <?= $tab === 'overview' ? 'is-active' : '' ?>" href="<?= e($tabUrl('overview')) ?>">Overview</a>
  <a class="tab <?= $tab === 'profile' ? 'is-active' : '' ?>" href="<?= e($tabUrl('profile')) ?>">Application answers</a>
  <a class="tab <?= $tab === 'onboarding' ? 'is-active' : '' ?>" href="<?= e($tabUrl('onboarding')) ?>">Documents &amp; checks</a>
  <a class="tab <?= $tab === 'activity' ? 'is-active' : '' ?>" href="<?= e($tabUrl('activity')) ?>">Activity</a>
</div>

<?php if ($tab === 'overview'): ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Readiness</h2><span class="grow"></span><span class="pill <?= $readiness['ready'] ? 'pill-accent' : '' ?>"><?= $readiness['ready'] ? 'Ready for decision' : Application::completionPercent($app) . '% complete' ?></span></div>
      <ul class="check-list">
        <?php foreach ([['agreementsDone', 'Volunteer agreement signed', $app['agreement_signed_at'] ? 'Signed ' . fmt_datetime($app['agreement_signed_at']) . ' (v' . $app['agreement_version'] . ')' : 'Not yet signed'], ['idsDone', 'ID documents uploaded', Application::eventsOnly($app) ? 'Not required (events-only role)' : ($readiness['idsDone'] ? 'Both documents on file · ' . ($app['id_verification_status'] ?: 'pending') : 'Waiting for upload')], ['cvDone', 'CV uploaded', Application::cvRequired($app) ? 'Required for this category' : 'Optional'], ['onboardingDone', 'Onboarding wizard completed', 'Stage ' . $app['onboarding_stage'] . ' of 5' . ($app['submitted_at'] ? ' · submitted ' . fmt_datetime($app['submitted_at']) : '')]] as [$k, $label, $sub]): ?>
        <li style="display:flex;gap:10px;align-items:flex-start;justify-content:flex-start"><span style="color:<?= $readiness[$k] ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($readiness[$k] ? 'check-circle' : 'circle', '', 18) ?></span><span><strong><?= e($label) ?></strong><br><span class="small muted"><?= e($sub) ?></span></span></li>
        <?php endforeach; ?>
        <?php if ($isCanada): ?>
        <li style="display:flex;gap:10px;align-items:flex-start;justify-content:flex-start"><span style="color:<?= $app['interview_status'] === 'completed' && $app['interview_outcome'] === 'passed' ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($app['interview_status'] === 'completed' && $app['interview_outcome'] === 'passed' ? 'check-circle' : 'circle', '', 18) ?></span><span><strong>Interview</strong><br><span class="small muted"><?= e(start_case($app['interview_status'])) ?><?= $app['interview_status'] === 'completed' ? ' · ' . e($app['interview_outcome']) : '' ?><?= $booked ? ' · booked ' . e(fmt_date($booked['slot_date'])) . ' ' . e($booked['slot_time']) : '' ?></span></span></li>
        <?php if (Application::riskAssessmentRequired($app)): ?><li style="display:flex;gap:10px;align-items:flex-start;justify-content:flex-start"><span style="color:<?= Application::riskAssessmentDone($app) ? 'var(--success)' : 'var(--muted)' ?>"><?= icon(Application::riskAssessmentDone($app) ? 'check-circle' : 'circle', '', 18) ?></span><span><strong>Risk assessment</strong><br><span class="small muted"><?= e($app['risk_assessment_status']) ?> · stage <?= e($app['risk_assessment_stage']) ?></span></span></li><?php endif; ?>
        <?php endif; ?>
      </ul>
    </div>
    <div class="card">
      <div class="card-head"><h2>Contact details</h2></div>
      <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="contact">
        <div class="grid-3">
          <div class="field"><label>Email</label><input name="email" value="<?= e($app['email']) ?>"></div>
          <div class="field"><label>Phone</label><input name="phone" value="<?= e($app['phone']) ?>"></div>
          <div class="field"><label>Country</label><input name="country" value="<?= e($app['country']) ?>"></div>
          <div class="field"><label>City</label><input name="city" value="<?= e($app['city']) ?>"></div>
          <div class="field"><label>Town</label><input name="town" value="<?= e($app['town']) ?>"></div>
          <div class="field"><label>Postal code</label><input name="postal_code" value="<?= e($app['postal_code']) ?>"></div>
        </div>
        <button class="btn btn-sm">Save contact details</button>
      </form>
    </div>
    <?php if ($isCanada): ?>
    <div class="card">
      <div class="card-head"><h2>Role approvals</h2><span class="grow"></span><span class="small muted">Approve each role the volunteer applied for.</span></div>
      <?php if (!$canadaRoles): ?><p class="muted small">No roles selected yet.</p><?php else: ?>
      <ul class="check-list"><?php foreach ($canadaRoles as $role): $ok = in_array($role, $app['onboarding_approved_roles'], true); ?>
        <li style="display:flex;gap:10px;align-items:center"><span class="chip"><?= e(Application::CA_ROLE_ABBR[$role] ?? '') ?></span><strong style="flex:1"><?= e($role) ?></strong>
          <?php if ($ok): ?><span class="badge badge-approved">Approved</span><?php endif; ?>
          <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="role" value="<?= e($role) ?>"><input type="hidden" name="value" value="<?= $ok ? '0' : '1' ?>"><button class="btn btn-sm <?= $ok ? 'btn-ghost' : 'btn-primary' ?>"><?= $ok ? 'Revoke' : 'Approve role' ?></button></form></li>
      <?php endforeach; ?></ul>
      <?php endif; ?>
      <details style="margin-top:12px"><summary class="small" style="cursor:pointer">Add or remove roles</summary>
        <form method="post" action="<?= $act ?>" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="manage_roles">
          <div class="choices"><?php foreach (Application::CA_ROLE_ABBR as $role => $ab): ?><label class="check"><input type="checkbox" name="roles[]" value="<?= e($role) ?>" <?= in_array($role, $canadaRoles, true) ? 'checked' : '' ?>> <?= e($role) ?></label><?php endforeach; ?></div>
          <div class="field-hint">Newly added roles are approved immediately and the volunteer receives the role welcome email.</div>
          <button class="btn btn-sm">Save roles</button>
        </form>
      </details>
    </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Notes &amp; history</h2><span class="grow"></span><span class="small muted"><?= count($notes) ?> entries</span></div>
    <form method="post" action="<?= $act ?>" style="margin-bottom:14px"><?= csrf_field() ?><input type="hidden" name="action" value="note">
      <div class="field"><textarea name="content" rows="3" placeholder="Add a note about this applicant…" required></textarea></div>
      <div class="actions"><select name="kind"><option value="note">Note</option><option value="event">Event / contact</option></select><button class="btn btn-sm btn-primary">Add</button></div>
    </form>
    <?php if (!$notes): ?><p class="empty">No notes yet.</p><?php else: ?>
    <ul class="timeline"><?php foreach ($notes as $n): ?>
      <li class="<?= $n['kind'] === 'status' ? 'is-done' : '' ?>"><span class="tl-dot"><?= icon(['status' => 'activity', 'event' => 'calendar'][$n['kind']] ?? 'message-circle', '', 14) ?></span><div><div><?= nl2br(e($n['content'])) ?></div><div class="small muted"><?= e($n['author_name'] ?: $n['author_email'] ?: 'System') ?> · <?= e(fmt_datetime($n['created_at'])) ?></div></div></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'profile'): ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card"><h2>About the applicant</h2><dl class="kv">
      <dt>Full name</dt><dd><?= e($app['name']) ?></dd><dt>Gender</dt><dd><?= e(ucfirst((string) $app['gender']) ?: '—') ?></dd>
      <dt>Date of birth</dt><dd><?= e(fmt_date($app['date_of_birth']) ?: '—') ?><?= $app['date_of_birth'] ? ' (age ' . Application::age($app['date_of_birth']) . ')' : '' ?> · 18+: <?= $yn($app['is_18_plus']) ?></dd>
      <dt>Phone</dt><dd><?= e($app['phone'] ?: '—') ?></dd><dt>WhatsApp</dt><dd><?= e($app['whatsapp'] ?: '—') ?></dd>
      <dt>Location</dt><dd><?= e(implode(', ', array_filter([$app['town'], $app['city'], $app['postal_code'], $app['country']])) ?: '—') ?></dd>
      <?php if ($app['detected_country']): ?><dt>Submitted from</dt><dd><?= e($app['detected_country']) ?></dd><?php endif; ?>
      <dt>University</dt><dd><?= e($app['university'] ?: '—') ?></dd>
      <dt>Islamic society</dt><dd><?= $yn($app['islamic_society_member']) ?><?= $app['islamic_society_name'] ? ' · ' . e($app['islamic_society_name']) : '' ?></dd>
      <dt>Languages</dt><dd><?= $list($app['languages']) ?></dd>
    </dl></div>
    <div class="card"><h2>Dawah journey</h2><dl class="kv">
      <dt>Involved in dawah</dt><dd><?= $yn($app['involved_in_dawah']) ?></dd>
      <?php if ($app['dawah_involvement_description']): ?><dt>Description</dt><dd><?= nl2br(e($app['dawah_involvement_description'])) ?></dd><?php endif; ?>
      <dt>Experience</dt><dd><?= e($app['dawah_experience_length']) ?></dd>
      <dt>Previous training</dt><dd><?= $yn($app['previous_training']) ?><?= $app['previous_training_details'] ? ' · ' . e($app['previous_training_details']) : '' ?></dd>
      <dt>Given shahadah</dt><dd><?= $yn($app['given_shahadah_before']) ?><?= $app['shahadah_count'] ? ' · ' . (int) $app['shahadah_count'] . ' times' : '' ?></dd>
      <dt>Motivation</dt><dd><?= $app['motivation'] ? nl2br(e($app['motivation'])) : '—' ?></dd>
    </dl></div>
  </div>
  <div class="card-stack">
    <div class="card"><h2>Skills &amp; availability</h2><dl class="kv">
      <dt>Skills</dt><dd><?= $list($app['skills']) ?><?= $app['custom_skill'] ? ' · ' . e($app['custom_skill']) : '' ?></dd>
      <dt>Roles</dt><dd><?= $list($app['role_interests']) ?></dd>
      <?php if ($isCanada): ?><dt>Approved roles</dt><dd><?= $list($app['onboarding_approved_roles']) ?></dd><?php endif; ?>
      <dt>Hours / week</dt><dd><?= e($app['hours_per_week'] ?: '—') ?> · weekly commitment: <?= $yn($app['weekly_commitment']) ?></dd>
      <dt>Preferred days</dt><dd><?= $list($app['preferred_days']) ?></dd>
      <dt>Social media</dt><dd><?php if ($app['no_social_media']): ?>None<?php elseif ($app['social_media']): foreach ($app['social_media'] as $k => $v): if (!$v) { continue; } ?><span class="chip"><?= e((Application::SOCIAL[$k][0] ?? $k) . ': ' . $v) ?></span><?php endforeach; else: ?>—<?php endif; ?></dd>
      <dt>Contact preferences</dt><dd><?= $list($app['communication_preferences']) ?></dd>
      <dt>Tags</dt><dd><?= $list($app['tags']) ?></dd>
    </dl></div>
    <div class="card"><h2>Application record</h2><dl class="kv">
      <dt>Created</dt><dd><?= e(fmt_datetime($app['created_at'])) ?></dd>
      <dt>Submitted</dt><dd><?= e(fmt_datetime($app['submitted_at']) ?: 'Not yet') ?></dd>
      <dt>Last activity</dt><dd><?= e(fmt_datetime($app['last_activity_at']) ?: '—') ?></dd>
      <dt>Terms</dt><dd>Terms <?= $yn($app['agreed_to_terms']) ?> · Data protection <?= $yn($app['agreed_data_protection']) ?> · Conflict of interest <?= $yn($app['agreed_conflict_of_interest']) ?></dd>
      <?php if (Application::isOnline($app)): ?><dt>Online dawah interest</dt><dd><?= $yn($app['online_dawah_interest']) ?></dd><?php endif; ?>
      <dt>User account</dt><dd><?= $target ? e($target['email']) . ' · ' . e(\App\Models\User::statusLabel($target)) : '<span class="muted">missing</span>' ?></dd>
    </dl></div>
    <?php if ($super): ?>
    <div class="card" style="border-color:var(--danger)">
      <h2 style="color:var(--danger)">Delete volunteer (GDPR)</h2>
      <p class="small muted">Removes the application, user account, documents, reports, posts, messages and notifications. This cannot be undone.</p>
      <form method="post" action="<?= $act ?>" data-confirm="Permanently delete this volunteer and all their data?"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <div class="field"><label>Type the full name to confirm: <em><?= e($app['name']) ?></em></label><input name="confirm_name" autocomplete="off"></div>
        <button class="btn btn-danger btn-sm"><?= icon('trash', '', 14) ?> Delete permanently</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'onboarding'): ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Identity documents</h2><span class="grow"></span><?php if ($app['id_verification_status']): ?><span class="badge badge-<?= e($badge($app['id_verification_status'])) ?>">ID <?= e($app['id_verification_status']) ?></span><?php endif; ?></div>
      <?php if (!Application::idRequired($app) && !$docs['id_doc_1'] && !$docs['id_doc_2']): ?><p class="small muted"><?= Application::eventsOnly($app) ? 'Not required: events-only role.' : 'ID documents are optional for this category.' ?></p><?php endif; ?>
      <?php foreach ([1, 2] as $n): $doc = $docs['id_doc_' . $n]; $st = $app["id_doc_{$n}_status"]; ?>
      <div class="doc-slot" style="margin-bottom:12px">
        <div><strong>Document <?= $n ?></strong> <span class="badge badge-<?= e($badge($st)) ?>"><?= e($st) ?></span><br><?= $docLink($doc) ?><?php if ($st === 'rejected' && $app["id_doc_{$n}_rejection_reason"]): ?><br><span class="small" style="color:var(--danger)">Reason: <?= e($app["id_doc_{$n}_rejection_reason"]) ?></span><?php endif; ?></div>
        <?php if ($doc): ?>
        <div class="actions">
          <?php if ($st !== 'approved'): ?><form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="doc_approve"><input type="hidden" name="doc" value="<?= $n ?>"><button class="btn btn-sm btn-primary">Approve</button></form><?php endif; ?>
          <details><summary class="btn btn-sm btn-ghost" style="list-style:none">Reject</summary><form method="post" action="<?= $act ?>" class="actions" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="doc_reject"><input type="hidden" name="doc" value="<?= $n ?>"><input class="input" name="reason" placeholder="Reason sent to the volunteer" required style="max-width:260px"><button class="btn btn-sm btn-danger">Reject &amp; email</button></form></details>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card"><div class="card-head"><h2>CV</h2></div><p><?= $docLink($docs['cv']) ?></p><?php if (!$docs['cv'] && Application::cvRequired($app)): ?><p class="small muted">A CV is required for this category; the volunteer is reminded on their dashboard.</p><?php endif; ?></div>
    <div class="card">
      <div class="card-head"><h2>Volunteer agreement</h2></div>
      <?php if ($signature): ?><dl class="kv"><dt>Signed by</dt><dd><?= e($signature['typed_signature']) ?></dd><dt>Signed at</dt><dd><?= e(fmt_datetime($signature['signed_at'])) ?></dd><dt>Version</dt><dd>v<?= e($signature['agreement_version']) ?></dd><dt>IP address</dt><dd><?= e($signature['ip_address'] ?: '—') ?></dd></dl>
      <details style="margin-top:10px"><summary class="small" style="cursor:pointer">Show agreement text as signed</summary><div class="field-copy" style="margin-top:8px;max-height:320px;overflow:auto;white-space:pre-wrap"><?= e($signature['agreement_text_snapshot']) ?></div></details>
      <?php else: ?><p class="muted">Not signed yet.</p><?php endif; ?>
    </div>
    <?php if ($isCanada): ?>
    <div class="card">
      <div class="card-head"><h2>Training &amp; certificates</h2></div>
      <?php if (Application::hasRole($app, 'Street Dawah') || Application::hasRole($app, 'Exhibitions')): ?>
      <form method="post" action="<?= $act ?>" class="field"><?= csrf_field() ?><input type="hidden" name="action" value="gorap">
        <label>GORAP training (Street Dawah / Exhibitions)</label>
        <div class="actions"><select name="mode"><?php foreach (['not_started' => 'Not started', 'completed_online' => 'Completed online', 'attended_in_person' => 'Attended in person'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $app['gorap_training_completion'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Save</button><?php if ($app['gorap_training_declared_at']): ?><span class="small muted">declared <?= e(fmt_datetime($app['gorap_training_declared_at'])) ?></span><?php endif; ?></div>
      </form>
      <?php endif; ?>
      <?php if (!$certKeys): ?><p class="small muted">No certificate-bearing roles selected.</p><?php endif; ?>
      <?php foreach ($certKeys as $key): $c = $app['certs'][$key] ?? []; $st = $c['status'] ?? 'missing'; ?>
      <div class="doc-slot" style="margin-bottom:12px">
        <div><strong><?= e(Application::CERT_LABELS[$key] ?? $key) ?></strong> <span class="badge badge-<?= e($badge($st)) ?>"><?= e($st) ?></span><br><?= $docLink($certDocs[$key] ?? null) ?><?php if (!empty($c['reviewed_by'])): ?><br><span class="small muted">reviewed by <?= e($c['reviewed_by']) ?> <?= e(fmt_datetime($c['reviewed_at'] ?? null)) ?></span><?php endif; ?></div>
        <form method="post" action="<?= $act ?>" enctype="multipart/form-data" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="cert"><input type="hidden" name="key" value="<?= e($key) ?>">
          <select name="status"><?php foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'pending' => 'Pending'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
          <input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png,.webp" style="max-width:200px"><button class="btn btn-sm">Save</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-stack">
    <?php if ($isCanada): ?>
    <div class="card">
      <div class="card-head"><h2>Interview</h2><span class="grow"></span><a class="small" href="<?= e(url('/admin/interviews?open=' . $app['id'])) ?>">Open in interview list</a></div>
      <?php if ($booked): ?><div class="flash flash-info" style="margin-bottom:12px">Booked slot: <strong><?= e(fmt_date($booked['slot_date'])) ?> at <?= e($booked['slot_time']) ?></strong> (<?= (int) $booked['duration_minutes'] ?> min)</div><?php endif; ?>
      <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="interview">
        <div class="grid-2">
          <div class="field"><label>Status</label><select name="interview_status"><?php foreach (['not_scheduled' => 'Not scheduled', 'scheduled' => 'Scheduled', 'completed' => 'Completed'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $app['interview_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Outcome</label><select name="interview_outcome"><?php foreach (['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed', 'hold' => 'On hold'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $app['interview_outcome'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Date</label><input type="date" name="interview_date" value="<?= e($app['interview_date'] ?: ($booked['slot_date'] ?? '')) ?>"></div>
          <div class="field"><label>Interviewer</label><input name="interviewer_name" value="<?= e($app['interviewer_name'] ?: \App\Models\Setting::get('connect_interviewer_name', '')) ?>"></div>
        </div>
        <div class="field"><label>Interview notes</label><textarea name="interview_notes" rows="4"><?= e($app['interview_notes']) ?></textarea></div>
        <button class="btn btn-sm btn-primary">Save interview</button>
        <span class="field-hint">Marking the interview as passed notifies the onboarding admin by email.</span>
      </form>
    </div>
    <?php if (Application::riskAssessmentRequired($app) || $app['risk_assessment_status'] !== 'Not Started'): ?>
    <div class="card">
      <div class="card-head"><h2>Risk assessment</h2><span class="grow"></span><span class="badge badge-<?= e(['Completed' => 'completed', 'In Progress' => 'pending', 'N/A' => 'default'][$app['risk_assessment_status']] ?? 'new') ?>"><?= e($app['risk_assessment_status']) ?></span></div>
      <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="ra">
        <div class="grid-2">
          <div class="field"><label>Stage</label><select name="risk_assessment_stage"><?php foreach (['Requested', 'Started', 'To Approval', 'Passed', 'Failed'] as $s): ?><option <?= $app['risk_assessment_stage'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Outcome</label><select name="risk_assessment_outcome"><?php foreach (['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed', 'query' => 'Query raised'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $app['risk_assessment_outcome'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Date</label><input type="date" name="risk_assessment_date" value="<?= e($app['risk_assessment_date']) ?>"></div>
          <div class="field"><label>Assessor</label><input name="risk_assessor_name" value="<?= e($app['risk_assessor_name']) ?>"></div>
        </div>
        <div class="field"><label>Notes</label><textarea name="risk_assessment_notes" rows="3"><?= e($app['risk_assessment_notes']) ?></textarea></div>
        <button class="btn btn-sm btn-primary">Save assessment</button>
      </form>
      <?php if ($app['risk_assessment_status'] !== 'N/A'): ?><form method="post" action="<?= $act ?>" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="ra_status"><input type="hidden" name="status" value="N/A"><button class="btn btn-sm btn-ghost">Mark not applicable</button></form><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (Application::hasRole($app, 'Street Dawah')): ?>
    <div class="card">
      <div class="card-head"><h2>Street dawah team referral</h2></div>
      <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="street_dawah">
        <div class="grid-2">
          <div class="field"><label>Team</label><input name="street_dawah_team" value="<?= e($app['street_dawah_team']) ?>"></div>
          <div class="field"><label>Location</label><input name="street_dawah_location" value="<?= e($app['street_dawah_location']) ?>"></div>
          <div class="field"><label>Amir</label><input name="street_dawah_amir" value="<?= e($app['street_dawah_amir']) ?>"></div>
          <div class="field"><label>Amir mobile</label><input name="street_dawah_amir_mobile" value="<?= e($app['street_dawah_amir_mobile']) ?>"></div>
        </div>
        <button class="btn btn-sm">Save referral</button>
      </form>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="card"><h2>Interview</h2><p class="small muted">Interviews and risk assessments apply to Canadian volunteers. Global and Online volunteers are approved from the Decisions panel once their documents are in order.</p></div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Engagement</h2><span class="grow"></span><span class="pill" style="border-color:<?= e($stats['level_color']) ?>;color:<?= e($stats['level_color']) ?>">Level <?= $stats['level'] ?> · <?= e($stats['level_title']) ?></span></div>
      <div class="stat-row">
        <div class="stat plain"><div class="stat-label">XP</div><div class="stat-value"><?= $stats['xp'] ?></div></div>
        <div class="stat plain"><div class="stat-label">Hours</div><div class="stat-value"><?= rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.') ?></div></div>
        <div class="stat plain"><div class="stat-label">Reports</div><div class="stat-value"><?= $stats['reports'] ?></div></div>
        <div class="stat plain"><div class="stat-label">Streak</div><div class="stat-value"><?= $stats['streak'] ?></div><div class="stat-sub">weeks</div></div>
        <div class="stat plain"><div class="stat-label">Badges</div><div class="stat-value"><?= count($stats['badges']) ?></div></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Impact reports</h2></div>
      <?php if (!$reports): ?><p class="empty">No impact reports logged.</p><?php else: ?>
      <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Activity</th><th>Hours</th><th>Summary</th></tr></thead><tbody>
      <?php foreach ($reports as $r): ?><tr><td><?= e(fmt_date($r['report_date'])) ?></td><td><?= e($r['activity_type']) ?></td><td><?= e($r['hours']) ?></td><td class="small"><?= e(mb_strimwidth((string) $r['summary'], 0, 120, '…')) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>Participation</h2><span class="grow"></span><a class="small" href="<?= e(url('/admin/participation-log')) ?>">Log participation</a></div>
      <?php if (!$participation && !$recruitment): ?><p class="empty">Nothing recorded yet.</p><?php else: ?>
      <ul class="check-list">
        <?php foreach ($participation as $p): ?><li><strong><?= e($p['task']) ?></strong> · <?= e(fmt_date($p['task_date'])) ?><?= $p['notes'] ? ' <span class="small muted">' . e($p['notes']) . '</span>' : '' ?></li><?php endforeach; ?>
        <?php foreach ($recruitment as $p): ?><li><strong><?= e($p['event_name']) ?></strong> · <?= e(fmt_date($p['event_date'])) ?> <span class="small muted">recruitment event</span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Communications sent</h2></div>
    <?php if (!$commLogs): ?><p class="empty">No bulk communications have been sent to this volunteer.</p><?php else: ?>
    <ul class="check-list"><?php foreach ($commLogs as $c): ?><li><strong><?= e($c['subject_line_sent'] ?: $c['template_name'] ?: 'Message') ?></strong><br><span class="small muted"><?= e($c['category'] ?: 'general') ?> · <?= e(fmt_datetime($c['sent_at'])) ?> · <?= e($c['delivery_status'] ?: 'sent') ?><?= $c['in_app_read'] ? ' · read' : '' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
