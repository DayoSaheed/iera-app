<?php use App\Models\Community; ?>
<button type="button" class="feedback-fab" data-open="feedbackModal" aria-label="Report an issue"><?= icon('alert-triangle', '', 16) ?> Report Issue</button>
<dialog class="modal" id="feedbackModal">
  <form method="post" action="<?= e(url('/feedback')) ?>" enctype="multipart/form-data" class="modal-body" data-feedback-form><?= csrf_field() ?>
    <h2>Report an Issue</h2><p class="muted small">Help us improve the platform.</p>
    <div class="field"><span class="field-label">Issue Type</span><div class="choices"><?php foreach (Community::ISSUE_TYPES as $t => $ico): ?><label class="choice"><input type="radio" name="issue_type" value="<?= e($t) ?>" <?= $t === 'Bug' ? 'checked' : '' ?>> <?= icon($ico, '', 12) ?> <?= e($t) ?></label><?php endforeach; ?></div></div>
    <div class="field"><label>Title <span class="req">*</span></label><input name="title" placeholder="Brief description of the issue" required></div>
    <div class="field"><label>Description <span class="req">*</span></label><textarea name="description" rows="4" placeholder="What happened? What did you expect to happen?" required></textarea></div>
    <div class="field"><span class="field-label">Priority</span><div class="choices"><?php foreach (['Low', 'Medium', 'High'] as $p): ?><label class="choice"><input type="radio" name="priority" value="<?= $p ?>" <?= $p === 'Medium' ? 'checked' : '' ?>> <?= $p ?></label><?php endforeach; ?></div></div>
    <div class="field"><label>Screenshot (optional)</label><input type="file" name="screenshot" accept="image/*"></div>
    <div class="field-copy small" data-feedback-context>Auto-captured context: page, browser, device and screen size are sent with your report.</div>
    <input type="hidden" name="page_url" data-fb-url><input type="hidden" name="page_affected" data-fb-path><input type="hidden" name="browser" data-fb-browser><input type="hidden" name="device_type" data-fb-device><input type="hidden" name="screen_size" data-fb-screen>
    <label class="check" style="margin-top:8px"><input type="checkbox" name="is_anonymous" value="1"><span>Submit anonymously</span></label>
    <div class="modal-actions"><button type="button" class="btn btn-ghost" data-close="feedbackModal">Cancel</button><button class="btn btn-primary"><?= icon('send', '', 14) ?> Send Feedback</button></div>
  </form>
</dialog>
