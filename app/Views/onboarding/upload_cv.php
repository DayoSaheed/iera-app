<?php $title = 'Upload Your CV'; $active = 'profile'; $breadcrumbs = ['Profile' => '/profile', $title => null]; ?>
<div class="card" style="max-width:720px">
  <p class="muted">Sharing your CV helps us understand your background and match you to the best volunteering roles.</p>
  <div class="help-box small"><strong>Tips for your CV:</strong> Keep it up to date · Include any relevant volunteer experience · Highlight transferable skills · PDF format preferred</div>
  <?php if ($docs['cv']): ?><p class="small" style="margin-top:12px"><?= icon('check-circle', '', 14) ?> CV uploaded: <a href="<?= e(url('/documents/' . $docs['cv']['id'])) ?>" target="_blank"><?= e($docs['cv']['filename']) ?></a>. Upload a new file to replace it.</p><?php endif; ?>
  <form method="post" action="<?= e(url('/upload-cv')) ?>" enctype="multipart/form-data" style="margin-top:12px"><?= csrf_field() ?>
    <div class="field"><label>CV file</label><input type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required><div class="field-hint">PDF, DOC, DOCX up to 10MB</div></div>
    <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/profile')) ?>">Back to Profile</a><button class="btn btn-primary"><?= icon('check', '', 14) ?> Save CV</button></div>
  </form>
</div>
