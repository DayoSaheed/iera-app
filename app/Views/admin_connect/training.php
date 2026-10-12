<?php $title = 'Training sync'; $active = 'cc-training'; ?>
<div class="two-col">
  <div class="card-stack">
    <div class="card">
      <div class="card-head"><h2>Auto sync</h2><span class="grow"></span><form method="post" action="<?= e(url('/admin/training-sync')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button class="btn btn-sm btn-primary" <?= $configured ? '' : 'disabled' ?>><?= icon('refresh', '', 14) ?> Run sync now</button></form></div>
      <p class="small muted">Runs every 6 hours automatically via the scheduler. Pulls completions of the Dawah Training course from the training site and awards the "Dawah Training Completed" badge (+100 XP).</p>
      <?php if (!$configured): ?><div class="flash flash-warning" style="margin:0"><strong>Not configured yet.</strong> Add the WordPress application password and LearnDash course ID under System admin → Settings → Integrations.</div><?php endif; ?>
      <?php if ($result): ?><div class="field-copy" style="margin-top:10px"><strong>Sync result</strong><br>
        <?php if (isset($result['error'])): ?><span style="color:var(--danger)"><?= e($result['error']) ?></span><?= !empty($result['hint']) ? '<br><span class="small muted">' . e($result['hint']) . '</span>' : '' ?>
        <?php else: ?>Awarded: <?= $result['awarded'] ? e(implode(', ', $result['awarded'])) : 'none' ?><br>Skipped (already awarded or unknown): <?= count($result['skipped']) ?><br>Errors: <?= count($result['errors']) ?><?= $result['errors'] ? '<br><span class="small muted">' . e(implode('; ', array_slice($result['errors'], 0, 5))) . '</span>' : '' ?><?php endif; ?>
      </div><?php endif; ?>
      <details style="margin-top:12px"><summary class="small" style="cursor:pointer">WordPress application password setup</summary>
        <ol class="small" style="padding-left:18px;margin-top:8px"><li>Log in to the training site's wp-admin.</li><li>Go to Users → Your Profile.</li><li>Scroll to Application Passwords and add one named "iERA Volunteer Platform".</li><li>Paste the 24-character password into System admin → Settings → Integrations → Training site application password.</li><li>Enter the WordPress username and the LearnDash course ID of the Dawah Training course.</li></ol>
      </details>
    </div>
    <div class="card">
      <h2>Manual badge award</h2>
      <p class="small muted">Award the "Dawah Training Completed" badge to a volunteer by email.<?= $modules ? '' : ' No training modules exist yet, so only the badge and XP are recorded.' ?></p>
      <form method="post" action="<?= e(url('/admin/training-sync')) ?>" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="award"><input class="input" type="email" name="email" placeholder="volunteer@example.org" required style="max-width:320px"><button class="btn btn-sm btn-primary">Award badge</button></form>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Award history</h2><span class="small muted"><?= count($awards) ?></span></div>
    <?php if (!$awards): ?><p class="empty">No awards yet. Run a sync or award a badge to get started.</p><?php else: ?>
    <ul class="check-list"><?php foreach ($awards as $w): ?><li><strong><?= e($w['email'] ?: 'deleted user') ?></strong><br><span class="small muted"><?= e($w['badge']) ?> · +<?= (int) $w['xp_awarded'] ?> XP · <?= $w['awarded_by'] === 'auto_sync' ? 'Auto' : 'Manual' ?> · <?= e(fmt_datetime($w['created_at'])) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
