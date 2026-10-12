<?php use App\Models\Application; $fullName = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?: $app['name']; ?>
<?php if (Application::agreementComplete($app)): ?>
<div class="card ob-card"><div class="all-clear"><?= icon('check-circle', '', 20) ?> You signed the Volunteer Agreement on <?= e(fmt_datetime($app['agreement_signed_at'])) ?> (version <?= e($app['agreement_version']) ?>).</div>
  <div class="ob-nav"><a class="btn btn-ghost" href="<?= e(url('/onboarding?stage=3')) ?>"><?= icon('chevron-left', '', 16) ?> Back</a><a class="btn btn-primary" href="<?= e(url('/onboarding?stage=5')) ?>">Continue to Activation <?= icon('chevron-right', '', 16) ?></a></div></div>
<?php else: ?>
<div class="card ob-card agreement" data-agreement>
  <div class="agreement-doc" data-agreement-doc>
    <div class="agreement-head"><img src="<?= e(\App\Models\Setting::logoUrl()) ?>" alt="" height="52"><hr><div class="agreement-title">VOLUNTEER AGREEMENT</div><div class="muted small">Version <?= e(\App\Services\Agreement::VERSION) ?> · Confidential</div></div>
    <?php foreach ($agreement as $s): ?>
      <h3><?= e($s['heading']) ?></h3>
      <?php foreach (array_filter(explode("\n\n", $s['body'])) as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
      <?php if ($s['bullets']): ?><ul><?php foreach ($s['bullets'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="agreement-status" data-agreement-status><?= icon('chevron-down', '', 16) ?> Scroll to the end to continue</div>
  <div class="agreement-gate" data-agreement-gate><strong>Read the full agreement above to continue</strong><br><span class="muted small">Scroll to the bottom of the document to unlock the signature section.</span></div>
  <form method="post" action="<?= e(url('/onboarding/sign')) ?>" class="agreement-sign" data-agreement-form hidden>
    <?= csrf_field() ?>
    <h3>Auto-filled Information</h3>
    <div class="grid-2">
      <div class="field"><label><?= icon('lock', '', 12) ?> Full Name (auto-filled)</label><input value="<?= e($fullName) ?>" disabled></div>
      <div class="field"><label><?= icon('lock', '', 12) ?> Date (auto-filled)</label><input value="<?= date('j F Y') ?>" disabled></div>
    </div>
    <h3>Your Pledge</h3>
    <div class="stack">
      <label class="check"><input type="checkbox" name="agreed_to_terms" value="1" data-pledge><span>I have read and understood the iERA Volunteer Agreement in full, and I agree to be bound by its terms and conditions.</span></label>
      <label class="check"><input type="checkbox" name="agreed_data_protection" value="1" data-pledge><span>I consent to iERA collecting, storing and processing my personal data in accordance with the Data Protection section of this Agreement and applicable UK data protection law.</span></label>
      <label class="check"><input type="checkbox" name="agreed_conflict_of_interest" value="1" data-pledge><span>I confirm I have no undisclosed conflicts of interest that would prevent me from fulfilling my volunteer role, and I agree to disclose any that may arise in the future.</span></label>
    </div>
    <div class="field" style="margin-top:16px"><label>Typed Signature <span class="req">*</span></label><div class="field-hint">Type your full name exactly as shown above to confirm your identity and sign this agreement.</div><input name="typed_signature" class="signature-input" placeholder='Type "<?= e($fullName) ?>" to sign' data-signature data-fullname="<?= e($fullName) ?>" autocomplete="off"><div class="field-error" data-sig-error hidden>Signature does not match your name. Please type it exactly as shown above.</div><div class="small" style="color:var(--success)" data-sig-ok hidden><?= icon('check', '', 12) ?> Signature verified — matches your registered name.</div></div>
    <div class="ob-nav"><a class="btn btn-ghost" href="<?= e(url('/onboarding?stage=3')) ?>"><?= icon('chevron-left', '', 16) ?> Back</a><button class="btn btn-primary btn-lg" data-sign-btn disabled><?= icon('check', '', 16) ?> Sign and Continue</button></div>
  </form>
</div>
<?php endif; ?>
