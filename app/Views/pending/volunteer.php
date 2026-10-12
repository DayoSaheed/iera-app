<?php
$labels = ['enrolled' => ['Application received', 'We have received your application to volunteer. JazakAllah khair!'], 'id_verified' => ['ID verification', ''], 'training_video' => ['Training video', ''], 'in_review' => ['In review', 'Our team is reviewing your application. You will receive an email once it is approved.'], 'approved' => ['Approved', 'Welcome to the team!']];
?>
<h1 class="auth-title">Application status</h1>
<p class="muted">Hello <?= e($u['first_name'] ?: $u['email']) ?>. Here is where your volunteer application stands.</p>
<ul class="timeline">
<?php foreach ($pipeline as $i => $key): $state = $i < $pos ? 'is-done' : ($i === $pos ? 'is-active' : ''); if ($key === 'approved') continue; ?>
  <li class="<?= $state ?>">
    <div class="tl-dot"><?= $i < $pos ? icon('check', '', 16) : $i + 1 ?></div>
    <div class="tl-body">
      <h3><?= e($labels[$key][0]) ?></h3>
      <?php if ($key === 'enrolled'): ?>
        <p><?= e($labels[$key][1]) ?></p>
      <?php elseif ($key === 'id_verified'): ?>
        <?php if ($i < $pos || $u['stripe_status'] === 'verified'): ?>
          <p>Your identity has been verified.</p>
        <?php elseif (!$stripeEnabled): ?>
          <p>Your ID will be checked by the admin team from the documents you uploaded. No action needed.</p>
        <?php elseif ($u['stripe_status'] === 'processing'): ?>
          <p>Your ID verification is being processed. This usually takes a few minutes.</p>
        <?php else: ?>
          <p><?= $u['stripe_status'] === 'requires_input' ? 'Your verification needs additional information.' : ($u['stripe_status'] === 'canceled' ? 'Your previous verification was cancelled. You can retry.' : 'Please verify your identity with a government ID.') ?></p>
          <a class="btn btn-primary btn-sm" href="<?= e(url('/pending/verify-id')) ?>">Verify my ID</a>
        <?php endif; ?>
      <?php elseif ($key === 'training_video'): ?>
        <?php if ($i < $pos): ?>
          <p>Training acknowledged on <?= e(fmt_date($u['training_date'])) ?>.</p>
        <?php else: ?>
          <p>Please watch the volunteer training video, then confirm below.</p>
          <?php if ($videoUrl): ?><div class="video-frame"><iframe src="<?= e($videoUrl) ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="Training video"></iframe></div><?php endif; ?>
          <form method="post" action="<?= e(url('/pending/training')) ?>" class="stack">
            <?= csrf_field() ?>
            <label class="check"><input type="checkbox" name="watched" value="1" required> <span>I have watched the training video</span></label>
            <button class="btn btn-primary btn-sm">Submit acknowledgement</button>
          </form>
        <?php endif; ?>
      <?php elseif ($key === 'in_review'): ?>
        <p><?= e($labels[$key][1]) ?></p>
        <?php if ($u['status'] === 'rejected'): ?><p class="no">Unfortunately your application was not approved. <?= e($u['comments'] ?? '') ?></p><?php endif; ?>
        <?php if ($u['status'] === 'paused'): ?><p>Your account is paused. Contact the admin team to reactivate it.</p><?php endif; ?>
      <?php endif; ?>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<p class="auth-help">Questions? Email <a href="mailto:<?= e(\App\Models\Setting::get('support_email')) ?>"><?= e(\App\Models\Setting::get('support_email')) ?></a> · <a href="<?= e(url('/profile')) ?>">Update my profile</a></p>
