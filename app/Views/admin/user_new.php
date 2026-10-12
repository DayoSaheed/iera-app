<?php $label = ['newmuslim' => 'New Muslim', 'org' => 'Organization', 'volunteer' => 'Volunteer'][$type]; $title = 'Add ' . $label; $active = 'admin'; $breadcrumbs = ['Admin Dashboard' => '/admin/users?type=' . $type, $title => null]; ?>
<div class="card" style="max-width:820px">
  <form method="post" action="<?= e(url('/admin/users/new?type=' . $type)) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>">
    <div class="field is-required"><label>Email <span class="req">*</span></label><input type="email" name="email" value="<?= old('email') ?>" required></div>
    <?php if ($isSuper && $type !== 'org'): ?><div class="field"><label>Teams</label><div class="choices"><?php foreach ($teams as $t): ?><label class="choice"><input type="checkbox" name="teams[]" value="<?= e($t['id']) ?>"> <span><?= e($t['name']) ?></span></label><?php endforeach; ?></div></div><?php endif; ?>
    <?= $formHtml ?>
    <p class="muted small"><?= $type === 'newmuslim' ? 'The New Muslim coordinator is notified and can assign a mentor. The person can sign in with their email at any time.' : 'The person signs in with their email address; no password is needed.' ?></p>
    <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/users?type=' . $type)) ?>">Cancel</a><button class="btn btn-primary">Create account</button></div>
  </form>
</div>
