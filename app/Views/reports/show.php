<?php
$kind = $r['type'] === 'mentor' ? 'mentor' : 'events';
$title = $context['title']; $active = 'reports-' . $kind . '-' . $r['status'];
$breadcrumbs = [($kind === 'mentor' ? 'Mentor' : 'Event') . ' reports' => '/reports/' . $kind . '/' . $r['status'], 'Report' => null];
$tplFields = \App\Models\FormTemplate::fields($r['type']);
?>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2><?= $r['status'] === 'submitted' ? 'Submitted report' : 'Submit report' ?></h2><span class="grow"></span><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></div>
    <p class="muted"><?= e($context['subtitle']) ?><?= $onBehalf ? ' · <strong>Submitting on behalf of ' . e(\App\Models\User::fullName($context['assignee'])) . '</strong>' : '' ?></p>
    <?php if ($r['status'] === 'submitted'): ?><p class="small muted">Submitted <?= e(fmt_datetime($r['submitted_at'])) ?><?= $r['submitted_by'] ? ' by a team lead' : '' ?>.<?= $viewOnly ? '' : ' You may correct and resubmit it.' ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('/reports/' . $r['id'])) ?>" novalidate>
      <?= csrf_field() ?>
      <?= $formHtml ?>
      <?php if (!$viewOnly): ?><div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/reports/' . $kind . '/' . $r['status'])) ?>">Back</a><button class="btn btn-primary"><?= $r['status'] === 'submitted' ? 'Update report' : 'Submit report' ?></button></div><?php endif; ?>
    </form>
  </div>
  <div class="card-stack">
    <?php if ($r['type'] === 'mentor' && !empty($context['mentee'])): $m = $context['mentee']; ?>
    <div class="card"><h3>Mentee</h3><dl class="kv"><dt>Name</dt><dd><?= e(\App\Models\User::fullName($m)) ?></dd><dt>Email</dt><dd><?= e($m['email']) ?></dd><dt>Phone</dt><dd><?= e(\App\Models\User::phoneOf($m)) ?></dd><dt>City</dt><dd><?= e($m['city']) ?></dd></dl>
      <?php if ($expectedLesson): ?><p class="help-box">Next lesson in sequence: <strong><?= e(\App\Models\FormTemplate::optionLabel('mentor', 'which_lesson', $expectedLesson)) ?></strong></p><?php endif; ?></div>
    <?php endif; ?>
    <div class="card"><h3>Past reports (<?= count($past) ?>)</h3>
      <?php if (!$past): ?><p class="muted">No earlier submitted reports.</p><?php else: ?>
      <?php foreach (array_slice($past, 0, 10) as $p): ?>
        <details class="nav-group" style="margin-bottom:8px"><summary><?= e(fmt_date($p['submitted_at'])) ?> · <?= e($p['assignee_name']) ?></summary>
          <dl class="kv" style="margin:8px 0 0 12px"><?php foreach ($tplFields as $f): if (empty($f['name']) || !isset($p['submit'][$f['name']]) || $p['submit'][$f['name']] === '' || $p['submit'][$f['name']] === null) continue; ?><dt><?= e($f['label']) ?></dt><dd><?= e(\App\Services\FormRenderer::displayValue($f, $p['submit'][$f['name']])) ?></dd><?php endforeach; ?></dl>
        </details>
      <?php endforeach; ?><?php endif; ?>
    </div>
  </div>
</div>
