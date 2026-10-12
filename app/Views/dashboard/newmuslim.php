<?php $title = 'Welcome'; $active = 'nm-home'; ?>
<div class="card">
  <h2>Assalamu alaikum, <?= e($u['first_name'] ?: 'and welcome') ?>!</h2>
  <?php if ($mentors): ?>
    <p>Your mentor<?= count($mentors) > 1 ? 's' : '' ?>: <?php foreach ($mentors as $m): ?><span class="chip"><?= e(trim($m['first_name'] . ' ' . $m['last_name'])) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['mobile'] ? ' · ' . e($m['mobile']) : '' ?></span><?php endforeach; ?></p>
  <?php else: ?>
    <p class="muted">A mentor will be assigned to you shortly. In the meantime, explore the resources below.</p>
  <?php endif; ?>
</div>
<div class="grid-3" style="margin-top:16px">
<?php foreach ($docs as $d): ?>
  <div class="card doc-card">
    <div class="doc-icon">📘</div>
    <div><h3><?= e($d['title']) ?></h3><p class="muted small"><?= e($d['desc']) ?></p><a class="btn btn-sm btn-primary" href="<?= e($base . rawurlencode($d['file'])) ?>" target="_blank" rel="noopener">View PDF</a></div>
  </div>
<?php endforeach; ?>
</div>
<?php if ($chat): ?><?= $chat ?><?php endif; ?>
