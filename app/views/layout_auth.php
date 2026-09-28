<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="bg-auth">
<main class="container py-4 py-md-5">
  <div class="text-center mb-4">
    <div class="auth-logo"><i class="bi bi-camera-reels"></i></div>
    <h1 class="h4 mt-2 mb-0"><?= e(APP_NAME) ?></h1>
    <div class="text-muted small">AD Ministério do Belém · Setor 124 Moema</div>
  </div>
  <div class="mx-auto auth-card<?= !empty($wide) ? ' wide' : '' ?>">
    <?php partial('flashes') ?>
    <?= $content ?>
  </div>
</main>
<footer class="text-center text-muted small py-3">
  <a href="<?= url('/termo-de-uso') ?>" class="text-muted">Termo de uso e privacidade</a>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/files.js') ?>"></script>
<script src="<?= asset('js/uploader.js') ?>"></script>
</body>
</html>
