<?php $title = 'Event Planning'; $active = 'planning'; ?>
<div class="card">
  <?php if (!$teams): ?><p class="empty">You do not lead any team.</p><?php else: ?>
  <table class="table"><thead><tr><th>Team</th><th>Members</th><th>Upcoming events</th><th></th></tr></thead><tbody>
  <?php foreach ($teams as $t): ?><tr data-href="<?= e(url('/events/planning/' . $t['id'])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong></td><td><?= (int) $t['members_count'] ?></td><td><?= (int) $t['active_events'] ?></td><td><a class="btn btn-sm" href="<?= e(url('/events/planning/' . $t['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</div>
