<?php
use App\Models\Application;
$cvReq = Application::cvRequired($app); $idReq = Application::idRequired($app); $eventsOnly = $uk && Application::eventsOnly($app);
$dash = fn($x) => $x === null || $x === '' || $x === [] ? '—' : (is_array($x) ? implode(', ', $x) : $x);
$ynText = fn($b) => $b === null ? '—' : ($b ? 'Yes' : 'No');
$canSubmit = !$allErrors && (!$cvReq || !empty($app['cv_doc_id']));
$docCard = function (string $slot, ?array $doc, string $label, string $hint, string $accept) use ($app) {
    $back = '/onboarding?stage=5';
    if ($doc) {
        return '<div class="doc-slot is-done"><span class="doc-slot-icon">' . icon('check-circle', '', 20) . '</span><div class="grow"><strong>' . e($label) . '</strong><br><small class="muted">' . e($doc['filename']) . '</small></div><a class="btn btn-sm btn-ghost" href="' . e(url('/documents/' . $doc['id'])) . '" target="_blank">View</a><form method="post" action="' . e(url('/onboarding/document/remove')) . '">' . csrf_field() . '<input type="hidden" name="slot" value="' . e($slot) . '"><input type="hidden" name="back" value="' . e($back) . '"><button class="btn btn-sm btn-ghost" aria-label="Remove">&times;</button></form></div>';
    }
    return '<form method="post" action="' . e(url('/onboarding/document')) . '" enctype="multipart/form-data" class="doc-slot" data-upload-slot>' . csrf_field() . '<input type="hidden" name="slot" value="' . e($slot) . '"><input type="hidden" name="back" value="' . e($back) . '"><span class="doc-slot-icon">' . icon('upload', '', 20) . '</span><div class="grow"><strong>' . e($label) . '</strong><br><small class="muted">' . e($hint) . '</small></div><label class="btn btn-sm btn-dark">Choose file<input type="file" name="file" accept="' . e($accept) . '" hidden data-upload-input></label></form>';
};
?>
<?php if ($cvReq): ?>
<div class="card ob-card">
  <h2><?= icon('file-text', '', 18) ?> CV <span class="req">*</span></h2>
  <p class="small muted">Please upload your CV — your application can't be submitted without one. If you don't have a CV, you can upload a covering letter instead. PDF, DOC or DOCX up to 10MB.</p>
  <?= $docCard('cv', $docs['cv'], 'CV or covering letter', 'PDF, DOC, DOCX up to 10MB', '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document') ?>
</div>
<?php endif; ?>
<?php if ($uk): ?>
<div class="card ob-card">
  <h2><?= icon('shield', '', 18) ?> ID & Proof of Address <?= $eventsOnly ? '<span class="muted small">(optional)</span>' : '<span class="req">*</span>' ?></h2>
  <p class="small muted"><?= $eventsOnly ? "As an Events volunteer you don't need to submit ID documents to apply — you're welcome to upload them anyway if you'd like them verified." : 'As a UK volunteer we need to verify your identity before your application can be submitted. Please upload one proof of ID and one proof of address.' ?></p>
  <?= $docCard('id_doc_1', $docs['id_doc_1'], 'Proof of ID' . ($eventsOnly ? '' : ' *'), 'Passport, driving licence, national ID card or biometric residence permit. JPG, PNG or PDF up to 10MB', 'image/*,.pdf') ?>
  <?= $docCard('id_doc_2', $docs['id_doc_2'], 'Proof of Address' . ($eventsOnly ? '' : ' *'), 'Utility bill, bank statement or council tax letter dated within the last 3 months. JPG, PNG or PDF up to 10MB', 'image/*,.pdf') ?>
  <?php if ($docs['id_doc_1'] && $docs['id_doc_2']): ?><div class="all-clear" style="margin-top:10px"><?= icon('check-circle', '', 18) ?> Both documents uploaded — will be submitted for verification</div><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($allErrors): ?><div class="flash flash-warning"><strong>Some sections are incomplete.</strong> Please fix the highlighted sections below before submitting.</div><?php endif; ?>

<?php $review = function (int $n, string $ico, string $heading, array $rows) use ($allErrors) { $errs = $allErrors[$n] ?? []; ?>
<div class="card ob-card review-card <?= $errs ? 'has-error' : '' ?>">
  <div class="card-head"><span class="ob-cover-icon"><?= icon($ico, '', 16) ?></span><h2><?= e($heading) ?></h2><span class="grow"></span><?php if ($errs): ?><span class="badge badge-rejected">Incomplete</span><?php endif; ?><a class="btn btn-sm btn-ghost" href="<?= e(url('/onboarding?stage=' . $n . '&from=review' . ($errs ? '&check=1' : ''))) ?>"><?= icon('edit', '', 14) ?> Edit</a></div>
  <dl class="kv"><?php foreach ($rows as $k => $val): ?><dt><?= e($k) ?></dt><dd><?= $val ?></dd><?php endforeach; ?></dl>
  <?php if ($errs): ?><a class="small" href="<?= e(url('/onboarding?stage=' . $n . '&from=review&check=1')) ?>">Fix <?= count($errs) ?> missing field<?= count($errs) > 1 ? 's' : '' ?> <?= icon('chevron-right', '', 12) ?></a><?php endif; ?>
</div>
<?php }; ?>
<?php
$about = ['Name' => e($dash($app['name'])), 'Gender' => e($dash($app['gender'])), 'Phone' => e($dash($app['phone'])), 'WhatsApp' => e($app['whatsapp'] ?: 'Same as phone'), 'Country' => e($dash($app['country'])), 'City' => e($dash($app['city'])), 'Town' => e($dash($app['town'])), 'Postal Code' => e($dash($app['postal_code'])), 'Date of Birth' => e($app['date_of_birth'] ? fmt_date($app['date_of_birth']) : '—')];
if ($uk) { $about['University'] = e($dash($app['university'])); $about['Islamic Society'] = e($app['islamic_society_member'] ? ($app['islamic_society_name'] ?: 'Yes') : 'No'); }
$about['Social media'] = !empty($app['no_social_media']) ? 'None' : e($dash(array_map(fn($k, $v) => "$k: $v", array_keys($app['social_media']), $app['social_media'])));
$review(1, 'user', 'About you', $about);
$review(2, 'globe', 'Your journey', ['Involved in dawah' => e($ynText($app['involved_in_dawah'])), 'Dawah experience' => e($dash($app['dawah_experience_length'])), 'Given shahadah' => e($app['given_shahadah_before'] ? 'Yes (' . ($app['shahadah_count'] ?? '?') . ')' : 'No'), 'Languages' => e($dash($app['languages'])), 'Motivation' => e(mb_strlen((string) $app['motivation']) > 100 ? mb_substr((string) $app['motivation'], 0, 100) . '…' : $dash($app['motivation']))]);
$skillsRows = ['Skills' => e($dash($app['skills'])), $uk ? 'Roles Applying For' : 'Role interests' => e($dash($app['role_interests']))];
if (Application::facebookRequired($app)) { $skillsRows['Facebook account'] = e($dash($facebook)); }
$skillsRows += ['Hours / week' => e($dash($app['hours_per_week'])), 'Weekly commitment' => e($ynText($app['weekly_commitment'])), 'Preferred days' => e($dash($app['preferred_days']))];
$review(3, 'wrench', 'Your skills', $skillsRows);
$review(4, 'clipboard', 'Your pledge', ['Volunteer Agreement' => $app['agreed_to_terms'] ? '<span class="yes">Agreed</span>' : '<span class="no">Not agreed</span>', 'Data Protection' => $app['agreed_data_protection'] ? '<span class="yes">Agreed</span>' : '<span class="no">Not agreed</span>', 'Conflict of Interest' => $app['agreed_conflict_of_interest'] ? '<span class="yes">Confirmed</span>' : '<span class="no">Not confirmed</span>']);
?>
<div class="card ob-card">
  <h2>What happens next?</h2>
  <ul class="ob-next">
    <li><?= icon('check-circle', '', 16) ?> Application submitted for review</li>
    <li><?= icon('mail', '', 16) ?> You'll receive a confirmation email</li>
    <?php if ($uk): ?><li><?= icon('shield', '', 16) ?> Your ID and proof of address will be verified by our team</li><?php endif; ?>
    <li><?= icon('clock', '', 16) ?> Once submitted, our team will review within <?= e(\App\Models\Setting::get('connect_review_days')) ?></li>
    <li><?= icon('zap', '', 16) ?> Once verified and approved, all features unlock</li>
  </ul>
</div>
<form method="post" action="<?= e(url('/onboarding/submit')) ?>" class="ob-nav"><?= csrf_field() ?>
  <a class="btn btn-ghost" href="<?= e(url('/onboarding?stage=3')) ?>"><?= icon('chevron-left', '', 16) ?> Back</a>
  <?php if ($app['submitted_at']): ?><span class="muted small">Submitted <?= e(fmt_datetime($app['submitted_at'])) ?>.</span> <a class="btn btn-dark" href="<?= e(url('/dashboard')) ?>">Go to dashboard</a><?php else: ?><button class="btn btn-primary btn-lg" <?= $canSubmit ? '' : 'disabled' ?>><?= icon('check', '', 16) ?> Submit my application</button><?php endif; ?>
</form>
