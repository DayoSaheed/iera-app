<?php $pos = $u['status'] === 'in_review' ? 1 : 0; ?>
<h1 class="auth-title">Registration status</h1>
<ul class="timeline">
  <li class="is-done"><div class="tl-dot"><?= icon('check', '', 16) ?></div><div class="tl-body"><h3>Received</h3><p>We have received your registration form.</p></div></li>
  <li class="is-active"><div class="tl-dot">2</div><div class="tl-body"><h3>In review</h3><p>A coordinator is matching you with a mentor.</p></div></li>
  <li><div class="tl-dot">3</div><div class="tl-body"><h3>Approved</h3><p>You will receive an email once your registration has been approved.</p></div></li>
</ul>
