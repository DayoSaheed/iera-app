<?php use App\Models\Application; $sm = $app['social_media']; $oldSm = $_SESSION['_old']['social_media'] ?? null; if (is_array($oldSm)) { $sm = $oldSm; } ?>
<section class="ob-section">
  <h2>Your details <small class="muted">— edit if anything needs correcting</small></h2>
  <div class="grid-2">
    <div class="field<?= $cls('first_name') ?>"><label>First Name <span class="req">*</span></label><input name="first_name" value="<?= $v('first_name') ?>" placeholder="First name"><?= $err('first_name') ?></div>
    <div class="field<?= $cls('last_name') ?>"><label>Last Name <span class="req">*</span></label><input name="last_name" value="<?= $v('last_name') ?>" placeholder="Last name"><?= $err('last_name') ?></div>
  </div>
  <div class="field"><label>Email Address</label><input value="<?= e($app['email']) ?>" disabled></div>
  <div class="field"><span class="field-label">Volunteer Type <span class="req">*</span></span>
    <div class="choice-cards">
      <?php foreach (['Global' => ['Volunteer (Global)', 'globe', 'For international physical outreach campaigns, local community events, and translation work anywhere in the world.'], 'UK' => ['Volunteer (UK)', 'flag', 'For university outreach, local UK masjid initiatives, national iERA events, and UK-based physical programs.'], 'Online' => ['Volunteer (Online Chat)', 'message-square', 'For digital content creation, social media, graphic design, online mentoring, and remote administrative support.']] as $val => [$label, $ico, $tip]): ?>
      <label class="choice-card <?= $app['volunteer_category'] === $val ? 'is-selected' : '' ?>" title="<?= e($tip) ?>"><input type="radio" name="volunteer_category" value="<?= e($val) ?>" <?= $app['volunteer_category'] === $val ? 'checked' : '' ?> data-reload-on-change><span class="choice-icon"><?= icon($ico, '', 20) ?></span><span><strong><?= e($label) ?></strong><small><?= e($tip) ?></small></span></label>
      <?php endforeach; ?>
    </div>
    <div class="field-hint">Changing your volunteer type updates the questions on the following steps.</div>
  </div>
  <div class="grid-2">
    <div class="field<?= $cls('gender') ?>"><span class="field-label">Gender <span class="req">*</span></span><div class="choices"><label class="choice"><input type="radio" name="gender" value="Male" <?= $v('gender') === 'Male' ? 'checked' : '' ?>> Male</label><label class="choice"><input type="radio" name="gender" value="Female" <?= $v('gender') === 'Female' ? 'checked' : '' ?>> Female</label></div><?= $err('gender') ?></div>
    <div class="field<?= $cls('date_of_birth') ?>"><label>Date of Birth <span class="req">*</span></label><input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= $v('date_of_birth') ?>" data-dob><div class="field-error" data-dob-warning hidden><?= icon('alert-triangle', '', 14) ?> You must be 18 or older to volunteer with iERA.</div><?= $err('date_of_birth') ?></div>
  </div>
  <div class="grid-2">
    <div class="field<?= $cls('phone') ?>"><label>Phone / WhatsApp <span class="req">*</span></label><input name="phone" value="<?= $v('phone') ?>" placeholder="+44 7123 456789"><?= $err('phone') ?></div>
    <?php if ($online): ?>
    <div class="field<?= $cls('whatsapp') ?>"><label>WhatsApp Number <span class="req">*</span> <span class="muted small">(Our Main Communication Platform)</span></label><input name="whatsapp" value="<?= $v('whatsapp') ?>" placeholder="+44 7123 456789"><?= $err('whatsapp') ?></div>
    <?php else: ?>
    <div class="field"><label>WhatsApp Number (if different from above)</label><input name="whatsapp" value="<?= $v('whatsapp') ?>" placeholder="Leave blank if same as your phone"></div>
    <?php endif; ?>
  </div>
</section>
<section class="ob-section">
  <h2>Your location</h2>
  <div class="grid-2">
    <div class="field<?= $cls('country') ?>"><label>Country <span class="req">*</span></label><input name="country" list="countries" value="<?= $v('country') ?>" placeholder="Start typing your country"><datalist id="countries"><?php foreach (Application::COUNTRIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist><?= $err('country') ?></div>
    <div class="field<?= $cls('city') ?>"><label>City <span class="req">*</span></label><input name="city" value="<?= $v('city') ?>" placeholder="Your city"><?= $err('city') ?></div>
    <div class="field"><label>Town</label><input name="town" value="<?= $v('town') ?>" placeholder="Your town / area"></div>
    <div class="field<?= $cls('postal_code') ?>"><label>Postal Code <span class="req">*</span></label><input name="postal_code" value="<?= $v('postal_code') ?>" placeholder="e.g. SW1A 1AA"><?= $err('postal_code') ?></div>
  </div>
</section>
<?php if ($uk): ?>
<section class="ob-section">
  <h2>UK Details</h2>
  <div class="field"><label>University</label><input name="university" list="universities" value="<?= $v('university') ?>" placeholder="Search your university"><datalist id="universities"><?php foreach (Application::UK_UNIVERSITIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
  <div class="field<?= $cls('islamic_society_member') ?>"><span class="field-label">Part of an Islamic Society? <span class="req">*</span></span><div class="choices"><label class="choice"><input type="radio" name="islamic_society_member" value="1" <?= $yn('islamic_society_member') === '1' ? 'checked' : '' ?> data-toggle="society"> Yes</label><label class="choice"><input type="radio" name="islamic_society_member" value="0" <?= $yn('islamic_society_member') === '0' ? 'checked' : '' ?> data-toggle="society"> No</label></div><?= $err('islamic_society_member') ?></div>
  <div class="field<?= $cls('islamic_society_name') ?>" data-show-when="islamic_society_member=1"><label>Society Name <span class="req">*</span></label><input name="islamic_society_name" value="<?= $v('islamic_society_name') ?>"><?= $err('islamic_society_name') ?></div>
</section>
<?php endif; ?>
<section class="ob-section<?= $cls('social_media') ?>">
  <h2>Your social media</h2>
  <p class="muted small">Share your accounts so we can connect with you. Add at least one link, or confirm below if you don't use social media.</p>
  <div class="grid-2" data-social-inputs>
    <?php foreach (Application::SOCIAL as $k => [$label, $ph]): ?>
    <div class="field<?= $k === 'other' ? ' span-2' : '' ?>"><label><?= e($label) ?><?= $online && $k === 'facebook' ? ' <span class="muted small">(required for Chat Agents and Mentors)</span>' : '' ?></label><input name="social_media[<?= e($k) ?>]" value="<?= e($sm[$k] ?? '') ?>" placeholder="<?= e($ph) ?>" <?= !empty($app['no_social_media']) ? 'disabled' : '' ?>></div>
    <?php endforeach; ?>
  </div>
  <label class="check"><input type="checkbox" name="no_social_media" value="1" <?= !empty($app['no_social_media']) ? 'checked' : '' ?> data-no-social><span>I don't have any social media accounts</span></label>
  <?= $err('social_media') ?>
</section>
