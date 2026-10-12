<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? \App\Core\Config::get('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=' . APP_VERSION)) ?>">
</head>
<body class="auth">
<div class="auth-bg"></div>
<main class="auth-wrap"><div class="card auth-card"><?= $content ?></div></main>
</body>
</html>
