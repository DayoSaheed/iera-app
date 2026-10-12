<?= \App\Core\View::partial('setup/_steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Setup complete</h1>
<p>You are signed in as <strong><?= e(\App\Models\User::fullName($u)) ?></strong>. Recommended next steps:</p>
<ol class="stack" style="padding-left:20px">
  <li><strong>Email delivery.</strong> System Admin → Settings → Email: enter your SMTP mailbox (or SendGrid key) and send a test email. Sign-in codes depend on it.</li>
  <li><strong>Scheduled jobs.</strong> Add a cPanel cron every 5 minutes: <code><?= e($cron) ?></code> or run jobs manually from System Admin → Scheduled jobs.</li>
  <li><strong>Settings.</strong> Notification emails, WhatsApp groups, documents URL and training video.</li>
  <li><strong>Teams and data.</strong> Create teams in People → Teams, or import the legacy data in System Admin → Import.</li>
  <li><strong>Security.</strong> Turn on two-step sign-in for admins in Settings → Security and set up your authenticator app in My Profile.</li>
</ol>
<div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/system/settings#Email')) ?>">Configure email</a><a class="btn btn-primary" href="<?= e(url('/system')) ?>">Open System Admin</a></div>
