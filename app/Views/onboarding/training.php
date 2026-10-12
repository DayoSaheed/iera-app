<?php use App\Models\Application; $title = 'Training'; $active = 'training'; $uk = $app && Application::isUk($app); $gorap = $app['gorap_training_completion'] ?? 'not_started'; $sdExh = $app && array_intersect($app['role_interests'], ['Street Dawah', 'Exhibitions']); ?>
<p class="muted" style="margin-top:-6px">Complete your Dawah training — certificates are only required for New Muslim Mentoring.</p>
<?php if ($app): ?>
<div class="card">
  <div class="card-head"><span class="ob-cover-icon"><?= icon('graduation-cap', '', 16) ?></span><h2>GORAP Dawah Training Course</h2></div>
  <?php if ($gorap === 'not_started'): ?>
    <p>Confirm you've completed the GORAP Dawah Training Course — this is required for your application to progress.</p>
    <?php if ($sdExh && $dawahCourse): ?><div class="help-box" style="margin-bottom:12px">As a Street Dawah / Exhibitions volunteer, sign up for the iERA Dawah training course to get started. <a class="btn btn-sm btn-dark" href="<?= e($dawahCourse) ?>" target="_blank" rel="noopener"><?= icon('external-link', '', 12) ?> Take the Dawah Training Course</a></div><?php endif; ?>
    <form method="post" action="<?= e(url('/training/gorap')) ?>"><?= csrf_field() ?>
      <div class="choice-cards">
        <label class="choice-card"><input type="radio" name="mode" value="completed_online" required><span class="choice-icon"><?= icon('globe', '', 20) ?></span><span><strong>Completed Online</strong><small>I completed the iERA GORAP Dawah Training Course online.</small></span></label>
        <label class="choice-card"><input type="radio" name="mode" value="attended_in_person"><span class="choice-icon"><?= icon('landmark', '', 20) ?></span><span><strong>Attended at a Mosque / Islamic Centre</strong><small>I attended the GORAP Dawah Training in person at a mosque or Islamic centre.</small></span></label>
      </div>
      <button class="btn btn-primary" style="margin-top:12px"><?= icon('check', '', 14) ?> Confirm Completion</button>
    </form>
  <?php else: ?>
    <div class="all-clear"><?= icon('check-circle', '', 18) ?> You've declared completion of the GORAP Dawah Training Course: <strong><?= $gorap === 'completed_online' ? 'Completed Online' : 'Attended at a Mosque / Islamic Centre' ?></strong><?= $app['gorap_training_declared_at'] ? ' · ' . e(fmt_date($app['gorap_training_declared_at'])) : '' ?></div>
    <form method="post" action="<?= e(url('/training/gorap')) ?>" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="mode" value="not_started"><button class="btn btn-sm btn-ghost"><?= icon('edit', '', 12) ?> Change</button></form>
  <?php endif; ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><span class="ob-cover-icon"><?= icon('award', '', 16) ?></span><div><h2>Role-Specific Training</h2><p class="small muted" style="margin:0">Complete the external training for your roles. Certificates are only required for New Muslim Mentoring.</p></div></div>
  <?php $renderCards = function (array $cards) use ($app) { ?>
  <div class="grid-2">
    <?php foreach ($cards as $key => [$ico, $ttl, $desc, $match, $suffix, $needsCert, $agreement, $note]): $cert = $app['certs'][$key] ?? null; $st = $cert['status'] ?? null; ?>
    <div class="train-card">
      <div class="train-head"><span class="ob-cover-icon"><?= icon($ico, '', 16) ?></span><strong><?= e($ttl) ?></strong><span class="grow"></span><?php if ($st === 'approved'): ?><span class="badge badge-ok">Approved</span><?php elseif ($st === 'pending'): ?><span class="badge badge-pending">Pending Review</span><?php endif; ?></div>
      <p class="small"><?= e($desc) ?></p>
      <?php if ($note): ?><p class="small" style="color:var(--warning);font-style:italic"><?= e($note) ?></p><?php endif; ?>
      <p class="small muted"><?= $needsCert ? 'Complete the training on the iERA website, then upload your certificate.' : 'Complete the training on the iERA website to prepare for this role.' ?></p>
      <a class="btn btn-sm btn-dark" href="<?= e(\App\Services\Onboarding::trainingUrl($suffix)) ?>" target="_blank" rel="noopener"><?= icon('external-link', '', 12) ?> Open Training</a>
      <?php if ($needsCert || $agreement): ?>
      <form method="post" action="<?= e(url('/training/cert/' . $key)) ?>" enctype="multipart/form-data" class="stack" style="margin-top:12px"><?= csrf_field() ?>
        <?php if ($agreement === 'mentor'): ?><div class="help-box small"><strong>Mentor Agreement</strong><br>I commit to supporting new Muslims with patience, empathy, and Islamic guidance. I will maintain confidentiality, refer complex cases to qualified scholars, and uphold the trust placed in me by iERA and the individuals I mentor.<br><label class="check" style="margin-top:6px"><input type="checkbox" name="mentor_agreement" value="1" <?= !empty($app['mentor_agreement_agreed']) ? 'checked' : '' ?>><span>I agree to the Mentor Agreement</span></label></div><?php endif; ?>
        <?php if ($agreement === 'nda'): ?><div class="help-box small"><strong>Non-Disclosure Agreement</strong><br>I understand that in my role as an iERA Admin Volunteer, I may have access to confidential information including volunteer personal data, internal communications, financial details, and operational strategies. I agree to keep all such information strictly confidential during and after my time as a volunteer. I will not share, disclose, or use this information for any purpose other than carrying out my assigned volunteer duties.<br><label class="check" style="margin-top:6px"><input type="checkbox" name="nda" value="1" <?= !empty($app['nda_agreed']) ? 'checked' : '' ?>><span>I agree to the NDA</span></label></div><?php endif; ?>
        <?php if ($needsCert): ?><div class="field"><label>Upload Completion Certificate <span class="pill pill-accent"><?= icon('zap', '', 10) ?> +10 XP</span></label><input type="file" name="certificate" accept="image/*,.pdf"><div class="field-hint">PDF or image</div></div><?php endif; ?>
        <button class="btn btn-sm btn-primary"><?= icon('check', '', 12) ?> <?= $needsCert ? 'Submit Certificate' : 'Confirm & Submit' ?></button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php }; ?>
  <?php if ($recommended): ?><div class="eyebrow" style="margin:8px 0"><?= icon('star', '', 12) ?> Recommended for you</div><?php $renderCards($recommended); ?><?php endif; ?>
  <?php if ($others): ?><div class="eyebrow" style="margin:18px 0 8px">Other roles you can train for</div><?php $renderCards($others); ?><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($completions): ?><div class="card" style="margin-top:16px"><h2>Completed training</h2><ul class="check-list"><?php foreach ($completions as $c): ?><li><span><?= e($c['course_name'] ?: 'Training module') ?></span><span class="muted small"><?= e(fmt_date($c['completed_at'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
