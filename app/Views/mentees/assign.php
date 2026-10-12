<?php $title = 'Assign mentors · ' . \App\Models\User::fullName($m); $active = 'admin'; $currentIds = array_column($current, 'mentor_id'); $back = $_GET['back'] ?? '/admin/users?type=newmuslim'; ?>
<div class="card" style="max-width:760px">
  <p class="muted"><?= e($m['email']) ?> · <?= e(\App\Models\User::phoneOf($m)) ?> · <?= e($m['city']) ?> · <?= e(ucfirst((string) $m['gender'])) ?></p>
  <form method="post" action="<?= e(url('/mentees/' . $m['id'] . '/assign')) ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="field"><label>Mentors (approved volunteers with the mentor role)</label>
      <input type="search" class="input" placeholder="Filter mentors…" oninput="const q=this.value.toLowerCase();this.nextElementSibling.querySelectorAll('.choice').forEach(c=>c.hidden=q&&!c.textContent.toLowerCase().includes(q))" style="margin-bottom:8px">
      <div class="choices"><?php foreach ($mentors as $mt): ?><label class="choice"><input type="checkbox" name="mentors[]" value="<?= e($mt['id']) ?>" <?= in_array($mt['id'], $currentIds, true) ? 'checked' : '' ?>> <span><?= e(\App\Models\User::fullName($mt)) ?> <small class="muted">(<?= e($mt['city'] ?: $mt['email']) ?>, <?= e(ucfirst((string) $mt['gender'])) ?>)</small></span></label><?php endforeach; ?></div>
    </div>
    <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url($back)) ?>">Cancel</a><button class="btn btn-primary">Save mentors</button></div>
  </form>
</div>
