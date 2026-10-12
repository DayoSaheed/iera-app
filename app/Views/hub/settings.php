<?php $title = 'Settings'; $active = 'settings-user'; $n = fn($k) => !isset($notify[$k]) || $notify[$k]; $c = fn($k) => !isset($comm[$k]) || $comm[$k]; ?>
<p class="muted" style="margin-top:-6px">Manage your account and notification preferences.</p>
<form method="post" action="<?= e(url('/settings')) ?>" class="card" style="max-width:760px"><?= csrf_field() ?>
  <h2><?= icon('mail', '', 16) ?> Contact Information</h2>
  <div class="grid-2">
    <div class="field"><label>Email Address</label><input value="<?= e($u['email']) ?>" disabled><div class="field-hint">Email cannot be changed</div></div>
    <div class="field"><label>Phone / WhatsApp</label><input type="tel" name="phone" value="<?= e($app['phone'] ?? $u['mobile'] ?? '') ?>" placeholder="+44 7123 456789"><div class="field-hint">Used for coordination and urgent updates</div></div>
  </div>
  <h2 style="margin-top:18px"><?= icon('bell', '', 16) ?> Notifications</h2>
  <div class="stack">
    <?php foreach (['notify_email' => ['mail', 'Email Notifications', 'Receive updates via email about applications, events, and important news'], 'notify_messages' => ['message-circle', 'Message Alerts', 'Get notified when you receive new direct messages'], 'notify_events' => ['calendar', 'Event Reminders', "Receive notifications about upcoming events you've registered for"], 'notify_training' => ['graduation-cap', 'Training Announcements', 'Get updates about new training modules and missions'], 'notify_streak' => ['flame', 'Streak Alerts', 'Reminder when your activity streak is about to reset']] as $k => [$ico, $l, $d]): ?>
    <label class="check pref-row"><input type="checkbox" name="<?= e($k) ?>" value="1" <?= $n($k) ? 'checked' : '' ?>><span><?= icon($ico, '', 14) ?> <strong><?= e($l) ?></strong><br><small class="muted"><?= e($d) ?></small></span></label>
    <?php endforeach; ?>
  </div>
  <?php if ($app): ?>
  <h2 style="margin-top:18px"><?= icon('mail-open', '', 16) ?> Email & Notification Preferences</h2>
  <p class="small muted">Choose which categories of communications you receive via email and in-app. Transactional emails (application updates) cannot be opted out of.</p>
  <div class="stack">
    <?php foreach (['event_updates' => ['calendar', 'Event updates & reminders', 'Upcoming event notifications, RSVPs, and day-before reminders'], 'weekly_updates' => ['bar-chart', 'Weekly updates', 'Regular newsletters and platform updates'], 'activity_reminders' => ['clock', 'Activity & hours reminders', 'Nudges to log your volunteering hours and activity chasers'], 'recognition' => ['trophy', 'Recognition & achievements', 'Badge awards, milestones, and leaderboard updates'], 'general_announcements' => ['megaphone', 'General announcements', 'Platform news, onboarding content, and general updates']] as $k => [$ico, $l, $d]): ?>
    <label class="check pref-row"><input type="checkbox" name="<?= e($k) ?>" value="1" <?= $c($k) ? 'checked' : '' ?>><span><?= icon($ico, '', 14) ?> <strong><?= e($l) ?></strong><br><small class="muted"><?= e($d) ?></small></span></label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="form-actions"><button class="btn btn-primary">Save Changes</button></div>
  <p class="small muted" style="margin:14px 0 0"><strong>Privacy & Data.</strong> Your settings are stored securely. We never share your contact information with third parties.</p>
</form>
