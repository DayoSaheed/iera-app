<?php $me = \App\Core\Auth::user(); if (!$me || !\App\Core\Auth::isOnboarded($me) || \App\Core\Auth::hasGroup($me, 'newmuslim')) { return; } ?>
<dialog class="modal" id="referModal">
  <form method="post" action="<?= e(url('/refer')) ?>" class="modal-body">
    <?= csrf_field() ?>
    <h2>Refer a New Muslim</h2>
    <p class="muted">We will create their account and notify the New Muslim coordinator, who assigns a mentor.</p>
    <div class="grid-2">
      <div class="field is-required"><label>First name <span class="req">*</span></label><input type="text" name="first_name" required></div>
      <div class="field"><label>Last name</label><input type="text" name="last_name"></div>
    </div>
    <div class="field is-required"><label>Email <span class="req">*</span></label><input type="email" name="email" required></div>
    <div class="grid-2">
      <div class="field"><label>Phone</label><input type="tel" name="phone" data-phone></div>
      <div class="field"><label>Gender</label><select name="gender"><option value="">Select…</option><option value="male">Male</option><option value="female">Female</option></select></div>
    </div>
    <div class="field"><label>City</label><input type="text" name="city" data-city-autocomplete list="refer_city_list" autocomplete="off"><datalist id="refer_city_list"></datalist></div>
    <div class="field"><label>Notes for the coordinator</label><textarea name="notes" rows="3"></textarea></div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" data-close="referModal">Cancel</button>
      <button class="btn btn-primary">Send referral</button>
    </div>
  </form>
</dialog>
