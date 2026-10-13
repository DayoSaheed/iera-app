<?php
use App\Models\Application;
use App\Services\AdminReview;
$title = 'Interviews'; $active = 'cc-interviews';
$labels = ['to_interview' => 'To interview', 'scheduled' => 'Scheduled', 'completed' => 'Completed'];
$script = ['Welcome the applicant, introduce yourself and iERA, and explain the purpose of the call (10–15 minutes, informal).', 'Confirm their details: name, location, the roles they applied for and availability.', 'Ask what drew them to iERA and to dawah work.', 'Ask about any previous dawah, volunteering or Islamic society experience.', 'Explore their understanding of the role(s) they chose and what a typical session looks like.', 'Discuss practical commitment: hours per week, preferred days, travel to events.', 'Check training: GORAP (street dawah / exhibitions), role certificates, willingness to attend training.', 'Safeguarding and conduct: explain the volunteer agreement, code of conduct and data protection expectations.', 'For fundraising or call-centre roles: explain the risk assessment and what it involves.', 'Ask about any support or adjustments they need to volunteer comfortably.', 'Explain next steps: ID verification, role approvals, onboarding to a local team and first activity.', 'Give them the chance to ask questions.', 'Thank them, confirm how and when they will hear back, and record your outcome below.'];
?>
<div class="card-head" style="margin-bottom:10px">
  <div class="tabs" style="margin:0;border:0"><?php foreach ($labels as $k => $l): ?><a class="tab <?= $tab === $k ? 'is-active' : '' ?>" href="<?= e(url('/admin/interviews?tab=' . $k)) ?>"><?= e($l) ?> <span class="nav-count" style="margin-left:6px"><?= count($tabs[$k]) ?></span></a><?php endforeach; ?></div>
  <span class="grow"></span>
  <a class="btn btn-sm" href="<?= e(url('/admin/interview-calendar')) ?>"><?= icon('calendar', '', 16) ?> Manage slots</a>
</div>
<div class="<?= $open ? 'two-col' : '' ?>">
  <div class="card">
    <form class="table-toolbar" method="get" action="<?= e(url('/admin/interviews')) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name, email, city…"><button class="btn btn-sm btn-dark">Search</button></form>
    <?php if (!$rows): ?><p class="empty"><?= $tab === 'to_interview' ? 'Nobody is waiting for an interview. Applicants appear here once their onboarding is complete.' : 'No interviews in this list.' ?></p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Applicant</th><th>Roles</th><th>Ready</th><th><?= $tab === 'completed' ? 'Outcome' : 'Interview' ?></th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $a): $r = AdminReview::readiness($a); ?>
        <tr <?= $open && $open['id'] === $a['id'] ? 'style="background:var(--accent-50)"' : '' ?>>
          <td><strong><?= e($a['name']) ?></strong><br><span class="small muted"><?= e($a['email']) ?><?= $a['city'] ? ' · ' . e($a['city']) : '' ?></span></td>
          <td><?php foreach (Application::canadaAbbrs($a) as $role => $ab): ?><span class="chip" title="<?= e($role) ?>"><?= e($ab) ?></span><?php endforeach; ?></td>
          <td><span style="color:<?= $r['ready'] ? 'var(--success)' : 'var(--muted)' ?>"><?= icon($r['ready'] ? 'check-circle' : 'clock', '', 16) ?></span></td>
          <td class="small"><?php if ($tab === 'completed'): ?><span class="badge badge-<?= e(['passed' => 'approved', 'failed' => 'rejected', 'hold' => 'paused'][$a['interview_outcome']] ?? 'pending') ?>"><?= e($a['interview_outcome']) ?></span> <?= e(fmt_date($a['interview_date'])) ?><?php else: ?><?= $a['interview_date'] ? e(fmt_date($a['interview_date'])) : '<span class="muted">not booked</span>' ?><?= $a['interviewer_name'] ? '<br><span class="muted">' . e($a['interviewer_name']) . '</span>' : '' ?><?php endif; ?></td>
          <td><a class="btn btn-sm" href="<?= e(url('/admin/interviews?tab=' . $tab . '&open=' . $a['id'] . ($q ? '&q=' . urlencode($q) : ''))) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <?php if ($open): $a = $open; ?>
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2><?= e($a['name']) ?></h2><span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/applications/' . $a['id'])) ?>">Full application</a></div>
      <dl class="kv"><dt>Email</dt><dd><?= e($a['email']) ?></dd><dt>Phone</dt><dd><?= e($a['phone'] ?: '—') ?></dd><dt>Location</dt><dd><?= e(trim(($a['city'] ?? '') . ', ' . ($a['country'] ?? ''), ', ') ?: '—') ?></dd><dt>Roles</dt><dd><?= e(implode(', ', Application::canadaRoles($a)) ?: '—') ?></dd><dt>Availability</dt><dd><?= e($a['hours_per_week'] ?: '—') ?> · <?= e(implode(', ', $a['preferred_days']) ?: '—') ?></dd><dt>Experience</dt><dd><?= e($a['dawah_experience_length']) ?></dd>
        <?php if ($openBooked): ?><dt>Booked slot</dt><dd><strong><?= e(fmt_date($openBooked['slot_date'])) ?> at <?= e($openBooked['slot_time']) ?></strong> (<?= (int) $openBooked['duration_minutes'] ?> min)</dd><?php endif; ?></dl>
      <?php if ($a['motivation']): ?><p class="small" style="margin-top:10px"><strong>Motivation:</strong> <?= e($a['motivation']) ?></p><?php endif; ?>
    </div>
    <div class="card">
      <details><summary style="cursor:pointer;font-weight:700">Interview script (13 steps)</summary>
        <ol style="padding-left:20px;margin-top:10px"><?php foreach ($script as $step): ?><li class="small" style="margin-bottom:6px"><?= e($step) ?></li><?php endforeach; ?></ol>
      </details>
    </div>
    <div class="card">
      <h2>Record the interview</h2>
      <form method="post" action="<?= e(url('/admin/applications/' . $a['id'] . '/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="interview"><input type="hidden" name="back" value="/admin/interviews?tab=<?= e($tab) ?>&open=<?= e($a['id']) ?>">
        <div class="grid-2">
          <div class="field"><label>Status</label><select name="interview_status"><?php foreach (['not_scheduled' => 'Not scheduled', 'scheduled' => 'Scheduled', 'completed' => 'Completed'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $a['interview_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Outcome</label><select name="interview_outcome"><?php foreach (['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed', 'hold' => 'On hold'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $a['interview_outcome'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Date</label><input type="date" name="interview_date" value="<?= e($a['interview_date'] ?: ($openBooked['slot_date'] ?? '')) ?>"></div>
          <div class="field"><label>Interviewer</label><input name="interviewer_name" value="<?= e($a['interviewer_name'] ?: \App\Models\Setting::get('connect_interviewer_name', '')) ?>"></div>
        </div>
        <div class="field"><label>Notes</label><textarea name="interview_notes" rows="5" placeholder="Key answers, concerns, recommended roles…"><?= e($a['interview_notes']) ?></textarea></div>
        <button class="btn btn-primary">Save interview</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
