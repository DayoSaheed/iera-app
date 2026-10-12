<?php use App\Services\Gamification; $title = 'Log Your Impact'; $active = 'impact'; ?>
<p class="muted" style="margin-top:-6px">Every hour counts. Every story matters.</p>
<div class="stat-row" style="margin-bottom:16px">
  <div class="stat primary"><div class="stat-label">Total reports</div><div class="stat-value"><?= $stats['reports'] ?></div></div>
  <div class="stat accent"><div class="stat-label">Total hours</div><div class="stat-value"><?= rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.') ?></div></div>
  <div class="stat info"><div class="stat-label">XP</div><div class="stat-value"><?= $stats['xp'] ?></div><div class="stat-sub">Level <?= $stats['level'] ?> · <?= e($stats['level_title']) ?></div></div>
  <?= \App\Core\View::partial('hub/_streak', ['stats' => $stats]) ?>
</div>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2>New Impact Log</h2><span class="pill pill-accent"><?= icon('zap', '', 12) ?> +20 XP</span><span class="grow"></span><button class="btn btn-sm btn-ghost" type="button" data-toggle-target="impactForm"><?= icon('plus', '', 14) ?> Log Impact</button></div>
  <form method="post" action="<?= e(url('/impact')) ?>" enctype="multipart/form-data" id="impactForm" <?= $open || !$reports ? '' : 'hidden' ?>><?= csrf_field() ?>
    <div class="grid-3">
      <div class="field"><label>Date <span class="req">*</span></label><input type="date" name="report_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
      <div class="field"><label>Hours <span class="req">*</span></label><input type="number" step="0.5" min="0.5" name="hours" placeholder="e.g. 3" required></div>
      <div class="field"><label>Activity Type <span class="req">*</span></label><select name="activity_type" required><option value="">What did you do?</option><?php foreach (Gamification::ACTIVITY_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label>Summary <span class="req">*</span></label><textarea name="summary" rows="3" placeholder="What did you do? Who did you help?" required></textarea></div>
    <div class="field"><label>Story / Reflection</label><textarea name="reflection" rows="3" placeholder="Any inspiring story or reflection? This could inspire others!"></textarea></div>
    <div class="field"><label>Photo <span class="muted small">(optional, +10 XP)</span></label><input type="file" name="photo" accept="image/*"></div>
    <div class="form-actions"><button class="btn btn-primary"><?= icon('send', '', 14) ?> Submit Impact</button></div>
  </form>
</div>
<?php if (!$reports): ?>
<div class="card" style="text-align:center;padding:40px"><h2>Start your impact journey</h2><p class="muted">Every report you submit brings you closer to your next badge.</p></div>
<?php else: ?>
<div class="card-stack">
  <?php foreach ($reports as $r): ?>
  <div class="card impact-card">
    <div class="impact-head"><span class="ob-cover-icon"><?= icon(Gamification::ACTIVITY_ICONS[$r['activity_type']] ?? 'pin', '', 16) ?></span><strong><?= e($r['activity_type']) ?></strong><span class="pill"><?= rtrim(rtrim(number_format((float) $r['hours'], 1), '0'), '.') ?>h</span><span class="pill pill-accent">+20 XP</span><?php if ($r['photo_doc_id']): ?><span class="pill"><?= icon('image', '', 12) ?> +10 XP</span><?php endif; ?><span class="grow"></span><span class="muted small"><?= e(fmt_date($r['report_date'])) ?></span></div>
    <p style="margin:8px 0 0"><?= nl2br(e($r['summary'])) ?></p>
    <?php if ($r['reflection']): ?><p class="muted" style="font-style:italic;margin:6px 0 0">“<?= nl2br(e($r['reflection'])) ?>”</p><?php endif; ?>
    <?php if ($r['photo_doc_id']): ?><a href="<?= e(url('/documents/' . $r['photo_doc_id'])) ?>" target="_blank"><img src="<?= e(url('/documents/' . $r['photo_doc_id'])) ?>" alt="" style="max-height:140px;border-radius:8px;margin-top:8px"></a><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
