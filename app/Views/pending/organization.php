<h1 class="auth-title">Registration status</h1>
<ul class="timeline">
  <li class="is-done"><div class="tl-dot"><?= icon('check', '', 16) ?></div><div class="tl-body"><h3>Received</h3><p>We have received your organization's registration.</p></div></li>
  <li class="is-active"><div class="tl-dot">2</div><div class="tl-body"><h3>In review</h3><p>An administrator is reviewing your account. You will receive an email once it is approved.</p></div></li>
  <li><div class="tl-dot">3</div><div class="tl-body"><h3>Approved</h3><p>You will be able to refer new Muslims and follow their progress.</p></div></li>
</ul>
<?php if ($u['status'] === 'rejected'): ?><p class="no">Unfortunately your registration was not approved. <?= e($u['comments'] ?? '') ?></p><?php endif; ?>
