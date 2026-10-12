<?php $title = 'Event Registration'; $active = 'register'; ?>
<div class="card">
  <?php if (!$teams): ?><p class="empty">You are not in a team yet.</p><?php else: ?>
  <table class="table"><thead><tr><th>Team</th><th>Upcoming events</th><th></th></tr></thead><tbody>
  <?php foreach ($teams as $t): ?><tr data-href="<?= e(url('/events/register/' . $t['id'])) ?>" class="row-link"><td><strong><?= e($t['name']) ?></strong></td><td><?= (int) $t['active_events'] ?></td><td><a class="btn btn-sm" href="<?= e(url('/events/register/' . $t['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</div>
