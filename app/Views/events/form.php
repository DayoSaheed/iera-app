<?php $active = 'planning'; $breadcrumbs = ['Event Planning' => '/events/planning', $team['name'] => '/events/planning/' . $team['id'], $title => null]; ?>
<div class="grid-2">
  <div class="card">
    <form method="post" action="<?= e(url($action)) ?>" novalidate>
      <?= csrf_field() ?>
      <?= $formHtml ?>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url($back)) ?>">Cancel</a><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
  <div class="card-stack">
    <?php if (!empty($event)): ?>
    <div class="card"><h3>Upcoming occurrences of this series</h3>
      <?php if (!$future): ?><p class="muted">None scheduled.</p><?php else: ?><ul class="check-list"><?php foreach ($future as $f): ?><li><?= e(fmt_datetime($f['start_at'])) ?><a class="btn btn-sm btn-ghost" href="<?= e(url('/sub-events/' . $f['id'] . '/edit')) ?>">Edit</a></li><?php endforeach; ?></ul><?php endif; ?>
      <p class="muted small">Editing the series updates the name, location and length of these occurrences. Setting the series to inactive cancels them.</p>
    </div>
    <?php elseif (!empty($sub)): ?>
    <div class="card"><h3>Registered volunteers</h3>
      <?php if (!$attendance): ?><p class="muted">Nobody yet.</p><?php else: ?><ul class="check-list"><?php foreach ($attendance as $a): ?><li><?= e($a['name']) ?><small class="muted"><?= e(implode(', ', $a['times'])) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
      <a class="btn btn-sm" href="<?= e(url('/attendance/' . $team['id'] . '/' . $sub['id'])) ?>">Manage attendance</a>
    </div>
    <?php else: ?>
    <div class="help-box"><strong>How series work.</strong> A recurring event keeps the chosen number of upcoming occurrences open (weekly, monthly or yearly from the start date). Volunteers register for each occurrence. When an occurrence ends, every registered volunteer receives a pending event report.</div>
    <?php endif; ?>
  </div>
</div>
