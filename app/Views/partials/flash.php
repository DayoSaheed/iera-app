<?php
$messages = \App\Core\Flash::pull();
$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
foreach ($messages as $m): ?>
<div class="flash flash-<?= e($m['type']) ?>" role="alert"><?= e($m['message']) ?><button type="button" class="flash-close" aria-label="Dismiss" onclick="this.parentNode.remove()">×</button></div>
<?php endforeach;
if ($errors): ?>
<div class="flash flash-error" role="alert"><strong>Please fix the following:</strong><ul><?php foreach ($errors as $field => $list): foreach ((array) $list as $msg): ?><li><?= e($msg) ?></li><?php endforeach; endforeach; ?></ul></div>
<?php endif; ?>
