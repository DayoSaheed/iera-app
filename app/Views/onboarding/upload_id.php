<?php $title = 'Upload ID Documents'; $active = 'profile'; $breadcrumbs = ['Profile' => '/profile', $title => null]; $st = $app['id_verification_status']; ?>
<div class="card" style="max-width:720px">
  <p class="muted">We need 2 forms of official ID to verify your identity. Accepted: passport, driving licence, national ID card. <span class="pill pill-accent"><?= icon('zap', '', 10) ?> +100 XP on approval</span></p>
  <?php if ($st === 'pending' || $st === 'approved'): ?>
    <div class="flash flash-warning"><strong><?= $st === 'approved' ? 'ID Verified' : 'Documents Submitted' ?></strong><br><?= $st === 'approved' ? 'Your identity has been confirmed.' : 'Your documents are under review. We will notify you once approved.' ?></div>
    <a class="btn btn-ghost" href="<?= e(url('/profile')) ?>">Back to Profile</a>
  <?php else: ?>
    <?php if ($st === 'rejected'): ?><div class="flash flash-error"><strong>ID Verification Rejected</strong><br><?php if ($app['id_doc_1_status'] === 'rejected'): ?>Document 1: <?= e($app['id_doc_1_rejection_reason'] ?: 'Rejected — please re-upload.') ?><br><?php endif; ?><?php if ($app['id_doc_2_status'] === 'rejected'): ?>Document 2: <?= e($app['id_doc_2_rejection_reason'] ?: 'Rejected — please re-upload.') ?><br><?php endif; ?><?php if ($app['id_doc_1_status'] !== 'rejected' && $app['id_doc_2_status'] !== 'rejected' && $app['id_rejection_reason']): ?>Reason: <?= e($app['id_rejection_reason']) ?><br><?php endif; ?>Please re-upload the rejected document(s) below and resubmit. Any already-approved document can be left as-is.</div><?php endif; ?>
    <p class="small"><strong>Accepted documents:</strong> Passport · Driving licence · National identity card · Biometric residence permit</p>
    <form method="post" action="<?= e(url('/upload-id')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
      <?php foreach (['id_doc_1' => 'Document 1 (e.g. Passport)', 'id_doc_2' => 'Document 2 (e.g. Driving Licence)'] as $slot => $label): $d = $docs[$slot]; $ds = $app[$slot . '_status']; ?>
      <div class="field"><label><?= e($label) ?> <?php if ($d): ?><span class="badge badge-<?= $ds === 'approved' ? 'ok' : ($ds === 'rejected' ? 'rejected' : 'pending') ?>"><?= e(ucfirst($ds)) ?></span><?php endif; ?></label>
        <?php if ($d && $ds !== 'rejected'): ?><div class="small muted">On file: <a href="<?= e(url('/documents/' . $d['id'])) ?>" target="_blank"><?= e($d['filename']) ?></a>. Choose a file only to replace it.</div><?php endif; ?>
        <input type="file" name="<?= e($slot) ?>" accept="image/*,.pdf"><div class="field-hint">JPG, PNG, PDF up to 10MB</div></div>
      <?php endforeach; ?>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/profile')) ?>">Back to Profile</a><button class="btn btn-primary"><?= icon('check', '', 14) ?> Submit for Review</button></div>
    </form>
  <?php endif; ?>
</div>
