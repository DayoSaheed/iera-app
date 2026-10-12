<?php $title = 'Messages'; $active = 'messages'; ?>
<div class="two-col">
  <div class="card-stack">
    <form method="post" action="<?= e(url('/messages')) ?>" class="card"><?= csrf_field() ?>
      <h2><?= icon('mail', '', 16) ?> New Message</h2>
      <div class="field"><select name="to_user_id" required><option value="">Select a volunteer to message…</option><?php foreach ($recipients as $r): ?><option value="<?= e($r['user_id']) ?>" <?= $to === $r['user_id'] ? 'selected' : '' ?>><?= e($r['name']) ?> (<?= e($r['email']) ?>)</option><?php endforeach; ?></select></div>
      <div class="actions"><input class="input" name="content" placeholder="Type your message…" required style="flex:1"><button class="btn btn-primary"><?= icon('send', '', 14) ?> Send</button></div>
    </form>
    <div class="card">
      <div class="tabs"><a class="tab <?= $tab === 'inbox' ? 'is-active' : '' ?>" href="<?= e(url('/messages')) ?>">Inbox (<?= count($inbox) ?>)</a><a class="tab <?= $tab === 'sent' ? 'is-active' : '' ?>" href="<?= e(url('/messages?tab=sent')) ?>">Sent (<?= count($sent) ?>)</a></div>
      <?php $rows = $tab === 'inbox' ? $inbox : $sent; ?>
      <?php if (!$rows): ?><p class="empty">No messages yet. <?= $tab === 'inbox' ? 'Your inbox is empty.' : "You haven't sent any messages." ?></p><?php else: ?>
      <ul class="msg-list"><?php foreach ($rows as $m): $name = $tab === 'inbox' ? $m['from_name'] : 'To: ' . (trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: ($m['to_email'] ?? '')); ?><li class="msg <?= $tab === 'inbox' && !(int) $m['is_read'] ? 'is-unread' : '' ?>"><span class="avatar"><?= e(mb_strtoupper(mb_substr(ltrim($name, 'To: '), 0, 1))) ?></span><div class="grow"><strong><?= e($name) ?></strong> <small class="muted"><?= e(fmt_datetime($m['created_at'])) ?></small><br><?= nl2br(e($m['content'])) ?></div></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
  <div class="card"><h2>Tips</h2><p class="small muted">Messages are private between you and the volunteer you choose. Keep conversations respectful and in line with the Volunteer Agreement. For urgent matters contact the team directly.</p></div>
</div>
