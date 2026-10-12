<?php $title = 'Share Your Story'; $active = 'stories'; $breadcrumbs = ['Stories' => '/stories', $title => null]; ?>
<?php if (!$allowed): ?>
<div class="card" style="max-width:640px"><h2><?= icon('lock', '', 16) ?> Complete Your Profile First</h2><p class="muted">You need to finish your volunteer onboarding before submitting stories.</p><a class="btn btn-primary" href="<?= e(url('/onboarding')) ?>">Complete Profile</a></div>
<?php else: ?>
<form method="post" action="<?= e(url('/stories/submit')) ?>" enctype="multipart/form-data" class="card" style="max-width:760px"><?= csrf_field() ?>
  <p class="muted">Inspire the ummah. Earn the Storyteller badge when approved.</p>
  <div class="field"><label>Story Title <span class="req">*</span></label><input name="title" placeholder="e.g. How we gave Shahadah in Lagos" required></div>
  <div class="grid-3">
    <div class="field"><label>Continent <span class="req">*</span></label><select name="continent" required><option value="">Select…</option><?php foreach (\App\Models\Community::CONTINENTS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Country</label><input name="country" placeholder="e.g. Nigeria"></div>
    <div class="field"><label>Activity Type</label><select name="activity_type"><option value="">Select…</option><?php foreach (\App\Services\Gamification::ACTIVITY_TYPES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="field"><label>Your Story <span class="req">*</span></label><textarea name="content" rows="7" placeholder="Share what happened, who was impacted, and what you learned…" required></textarea></div>
  <div class="field"><label>Photo (optional)</label><input type="file" name="photo" accept="image/*"></div>
  <label class="check"><input type="checkbox" name="permission_granted" value="1" required><span>I give iERA permission to publish this story on their platforms. <span class="req">*</span></span></label>
  <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/stories')) ?>">Back to Stories</a><button class="btn btn-primary"><?= icon('edit', '', 14) ?> Submit Story</button></div>
</form>
<?php endif; ?>
