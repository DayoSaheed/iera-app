<?php /** @var array $issues */ $levels = ['error' => ['Fix now', 'x-circle'], 'warning' => ['Should fix', 'alert-triangle'], 'info' => ['Suggested', 'info']]; ?>
<?php if (!$issues): ?>
<div class="all-clear"><?= icon('check-circle', '', 20) ?> Setup looks good: nothing needs fixing right now.</div>
<?php else: ?>
<ul class="issue-list">
  <?php foreach ($issues as $i): [$tag, $ic] = $levels[$i['level']]; ?>
  <li class="issue is-<?= e($i['level']) ?>">
    <span class="issue-icon"><?= icon($ic, '', 20) ?></span>
    <div class="issue-text"><div class="issue-title"><?= e($i['title']) ?> <span class="badge issue-tag"><?= e($tag) ?></span></div><div class="issue-detail"><?= e($i['detail']) ?></div></div>
    <a class="btn btn-sm <?= $i['level'] === 'error' ? 'btn-dark' : 'btn-ghost' ?>" href="<?= e(url($i['href'])) ?>"><?= e($i['action']) ?></a>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
