<?php $title = 'Request admin access'; $active = 'cc'; ?>
<div class="card" style="max-width:640px">
  <h2>Request admin access</h2>
  <?php if ($hasAccess): ?>
    <div class="all-clear"><?= icon('check-circle', '', 18) ?> You already have admin access. <a href="<?= e(url('/admin')) ?>">Open the control centre</a></div>
  <?php elseif ($pending): ?>
    <div class="flash flash-warning" style="margin:0"><strong>Request pending.</strong> You asked for <em><?= e(start_case($pending['requested_role'])) ?></em> access on <?= e(fmt_date($pending['created_at'])) ?>. A superadmin will review it; you will be notified by email and in the app.</div>
  <?php else: ?>
    <p class="muted">Admin tools are limited to approved staff. Tell us which role you need and why, and a superadmin will review your request.</p>
    <form method="post" action="<?= e(url('/admin-signup')) ?>"><?= csrf_field() ?>
      <div class="field"><label>Your name</label><input value="<?= e(\App\Models\User::fullName($u)) ?>" disabled></div>
      <div class="field"><label>Email</label><input value="<?= e($u['email']) ?>" disabled></div>
      <div class="field"><label>Role requested</label><select name="requested_role">
        <option value="admin">Admin (full control centre)</option>
        <option value="mosque_coordinator">Mosque Coordinator</option>
        <option value="exhibition_contacts">Exhibition Contacts</option>
      </select></div>
      <div class="field"><label>Why do you need access?</label><textarea name="reason" rows="4" placeholder="Your role in iERA and what you will use the admin tools for"></textarea></div>
      <button class="btn btn-primary">Send request</button>
    </form>
  <?php endif; ?>
</div>
