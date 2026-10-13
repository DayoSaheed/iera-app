<?php $title = 'Microsoft Teams'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'teams']) ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Connection</h2><span class="grow"></span>
        <?php if ($enabled): ?><span class="badge badge-active">Connected</span><?php elseif ($on): ?><span class="badge badge-rejected">Webhook missing</span><?php else: ?><span class="badge badge-default">Off</span><?php endif; ?>
      </div>
      <p class="small muted">Everything recorded in the app is posted to your Teams channel as a card: new applications and registrations, reports, events and attendance, community activity and admin changes. Choose what to send under <a href="<?= e(url('/system/settings#Microsoft Teams')) ?>">Settings → Microsoft Teams</a>.</p>
      <dl class="kv">
        <dt>Status</dt><dd><?= $enabled ? 'Enabled and configured' : ($on ? 'Enabled, but no webhook URL has been saved' : 'Switched off') ?></dd>
        <dt>Last card sent</dt><dd><?= e(fmt_datetime($stats['last_sent']) ?: 'never') ?></dd>
        <dt>Sent in last 24h</dt><dd><?= $stats['sent_24h'] ?></dd>
        <dt>Waiting</dt><dd><?= $stats['pending'] ?> pending · <?= $stats['failed'] ?> failed</dd>
        <?php if ($stats['last_error']): ?><dt>Last error</dt><dd><?= e($stats['last_error']) ?></dd><?php endif; ?>
      </dl>
      <div class="actions" style="margin-top:14px">
        <form method="post" action="<?= e(url('/system/teams/test')) ?>"><?= csrf_field() ?><button class="btn btn-primary" <?= $enabled ? '' : 'disabled' ?>><?= icon('send', '', 14) ?> Send a test card</button></form>
        <form method="post" action="<?= e(url('/system/teams/retry')) ?>"><?= csrf_field() ?><button class="btn" <?= $enabled ? '' : 'disabled' ?>><?= icon('refresh', '', 14) ?> Retry pending &amp; failed</button></form>
        <a class="btn btn-ghost" href="<?= e(url('/system/settings#Microsoft Teams')) ?>"><?= icon('settings', '', 14) ?> Settings</a>
      </div>
    </div>
    <div class="card">
      <h2>How to connect a channel</h2>
      <ol class="small" style="padding-left:18px;margin:0">
        <li>In Microsoft Teams open the channel that should receive the updates (for example <em>#volunteer-activity</em>).</li>
        <li>Click the <strong>⋯</strong> next to the channel name and choose <strong>Workflows</strong>, then <strong>Post to a channel when a webhook request is received</strong>. (On older tenants: Connectors → Incoming Webhook.)</li>
        <li>Give it a name such as <em>iERA App</em>, pick the channel and finish. Teams shows a long <strong>https://…</strong> URL: copy it.</li>
        <li>Paste the URL into <a href="<?= e(url('/system/settings#Microsoft Teams')) ?>">Settings → Microsoft Teams</a>, tick <em>Post changes to Microsoft Teams</em>, choose what to send and save.</li>
        <li>Come back here and press <strong>Send a test card</strong>. The card should appear in the channel within a few seconds.</li>
      </ol>
      <p class="small muted" style="margin:12px 0 0">Cards are sent immediately as changes happen. If Teams is unreachable, they wait in the queue and the scheduler retries every 5 minutes. Categories: <?= e(implode(' · ', array_column($categories, 0))) ?>.</p>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Recent cards</h2><span class="grow"></span><span class="small muted"><?= count($rows) ?> shown</span></div>
    <?php if (!$rows): ?><p class="empty">Nothing has been sent yet.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Card</th><th>Category</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="small"><?= e(fmt_datetime($r['created_at'])) ?></td><td><strong><?= e($r['title']) ?></strong><?= $r['action'] ? '<br><span class="small muted">' . e($r['action']) . '</span>' : '' ?></td><td><span class="chip"><?= e($categories[$r['category']][0] ?? $r['category']) ?></span></td>
        <td><span class="badge badge-<?= ['sent' => 'ok', 'failed' => 'failed', 'pending' => 'pending'][$r['status']] ?? 'default' ?>"><?= e($r['status']) ?></span><?= $r['last_error'] ? '<br><span class="small muted">' . e(mb_strimwidth($r['last_error'], 0, 120, '…')) . '</span>' : '' ?><?= (int) $r['attempts'] > 1 ? '<br><span class="small muted">' . (int) $r['attempts'] . ' attempts</span>' : '' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>
