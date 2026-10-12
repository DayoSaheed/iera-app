<?php $title = 'Event Attendance'; $active = 'attendance'; ?>
<div class="card"><?php if (!$teams): ?><p class="empty">You do not lead any team.</p><?php else: ?>
<table class="table"><thead><tr><th>Team</th><th>Members</th><th>Past events</th><th></th></tr></thead><tbody>
<?php foreach ($teams as $t): ?><tr data-href="<?= e(url('/attendance/' . $t['id'])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong></td><td><?= (int) $t['members_count'] ?></td><td><?= (int) $t['active_events'] ?></td><td><a class="btn btn-sm" href="<?= e(url('/attendance/' . $t['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?></div>
