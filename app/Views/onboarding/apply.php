<?php $title = 'Join iERA Volunteers'; $sel = old('volunteer_category', $category); ?>
<div class="ob-narrow">
  <div class="ob-hero-text">
    <div class="eyebrow">Quick sign-up</div>
    <h1>Join iERA Volunteers</h1>
    <p class="muted">Takes less than 60 seconds. Complete your profile after joining.</p>
    <span class="pill pill-accent"><?= icon('zap', '', 14) ?> +50 XP when you join</span>
  </div>
  <form method="post" action="<?= e(url('/apply')) ?>" class="card ob-card" novalidate data-apply>
    <?= csrf_field() ?>
    <div class="grid-2">
      <div class="field"><label>First Name <span class="req">*</span></label><input name="first_name" value="<?= old('first_name', $u['first_name'] ?? '') ?>" placeholder="First name" required></div>
      <div class="field"><label>Last Name <span class="req">*</span></label><input name="last_name" value="<?= old('last_name', $u['last_name'] ?? '') ?>" placeholder="Last name" required></div>
    </div>
    <div class="field"><label>Email Address</label><input value="<?= e($u['email']) ?>" disabled><div class="field-hint">Email auto-filled from your account</div></div>
    <div class="field"><span class="field-label">Volunteer Type <span class="req">*</span></span>
      <div class="choice-cards">
        <?php foreach ($categories as $val => [$label, $sub, $ico]): ?>
        <label class="choice-card <?= $sel === $val ? 'is-selected' : '' ?>"><input type="radio" name="volunteer_category" value="<?= e($val) ?>" <?= $sel === $val ? 'checked' : '' ?> data-cat><span class="choice-icon"><?= icon($ico, '', 22) ?></span><span><strong><?= e($label) ?></strong><small><?= e($sub) ?></small></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field"><span class="field-label">Gender <span class="req">*</span></span>
      <div class="choices"><label class="choice"><input type="radio" name="gender" value="Male" <?= old('gender') === 'Male' ? 'checked' : '' ?>> Male</label><label class="choice"><input type="radio" name="gender" value="Female" <?= old('gender') === 'Female' ? 'checked' : '' ?>> Female</label></div>
    </div>
    <div class="field" data-show-cat="UK,Online,"><label>Date of Birth <span class="req">*</span></label><input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= old('date_of_birth') ?>"><div class="field-hint">You must be 18 or older to volunteer with iERA.</div></div>
    <div class="field" data-show-cat="Global"><label class="check"><input type="checkbox" name="is_18_plus" value="1" <?= old('is_18_plus') ? 'checked' : '' ?>><span>I confirm that I am 18 years of age or older <span class="req">*</span></span></label></div>
    <div class="field"><label class="check"><input type="checkbox" name="agreed" value="1" <?= old('agreed') ? 'checked' : '' ?>><span>I agree to create an iERA volunteer account and continue profile setup. I understand I'll complete the full volunteer agreement during onboarding.</span></label></div>
    <button class="btn btn-primary btn-lg btn-block">Join the Mission <?= icon('chevron-right', '', 16) ?></button>
  </form>
  <div class="ob-stats"><div><b>1 min</b><span>to sign up</span></div><div><b>Free</b><span>always</span></div><div><b>Global</b><span>community</span></div></div>
</div>
<?php $scripts = '<script>document.addEventListener("DOMContentLoaded",()=>{const f=document.querySelector("[data-apply]");const sync=()=>{const v=(f.querySelector("[data-cat]:checked")||{}).value||"";f.querySelectorAll("[data-show-cat]").forEach(el=>{el.hidden=!el.getAttribute("data-show-cat").split(",").includes(v);});f.querySelectorAll(".choice-card").forEach(c=>c.classList.toggle("is-selected",c.querySelector("input").checked));};f.addEventListener("change",sync);sync();});</script>'; ?>
