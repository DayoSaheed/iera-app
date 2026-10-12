<h1 class="auth-title"><?= e($title) ?></h1>
<p class="muted"><?= e($intro) ?></p>
<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <?= $formHtml ?>
  <?php if (!str_contains($formHtml, 'data-stepper')): ?><div class="form-actions"><button class="btn btn-primary btn-lg">Submit</button></div><?php endif; ?>
</form>
