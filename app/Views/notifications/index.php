<?php $title = 'Notifications'; $active = 'notifications'; ?>
<div class="card">
  <div class="card-head">
    <div class="switch"><a class="<?= $filter !== 'unread' ? 'is-active' : '' ?>" href="<?= e(url('/notifications')) ?>">All</a><a class="<?= $filter === 'unread' ? 'is-active' : '' ?>" href="<?= e(url('/notifications?filter=unread')) ?>">Unread (<?= (int) $unread ?>)</a></div>
    <span class="grow"></span>
    <?php if ($unread): ?><form method="post" action="<?= e(url('/notifications/read')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= icon('check', '', 14) ?> Mark all as read</button></form><?php endif; ?>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/notifications/preferences')) ?>"><?= icon('settings', '', 14) ?> Preferences</a>
  </div>
  <?php if (!$rows): ?><p class="empty">Nothing here yet. Notifications about your application, events, training and community activity will appear here.</p><?php else: ?>
  <ul class="notif-list">
    <?php foreach ($rows as $r): ?>
    <li class="notif <?= (int) $r['is_read'] ? '' : 'is-unread' ?> is-<?= e($r['type']) ?>">
      <span class="notif-icon"><?= icon($r['icon'] ?: ['success' => 'check-circle', 'warning' => 'alert-triangle', 'danger' => 'x-circle'][$r['type']] ?? 'bell', '', 18) ?></span>
      <div class="notif-text"><div class="notif-title"><?= e($r['title']) ?></div><?php if ($r['message']): ?><div class="notif-msg"><?= nl2br(e($r['message'])) ?></div><?php endif; ?><div class="notif-when"><?= e(fmt_datetime($r['created_at'])) ?> · <?= e(\App\Models\Notification::CATEGORIES[$r['category']] ?? $r['category']) ?></div></div>
      <form method="post" action="<?= e(url('/notifications/' . $r['id'] . '/read')) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $r['link'] ? 'btn-dark' : 'btn-ghost' ?>"><?= $r['link'] ? 'Open' : ((int) $r['is_read'] ? 'Read' : 'Mark read') ?></button></form>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
