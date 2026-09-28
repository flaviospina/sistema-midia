<?php /** @var string $content */ // Layout de impressão (etiquetas com QR Code): sem menu, sem rodapé ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="bg-white">
<div class="d-print-none border-bottom mb-3 py-2 px-3 d-flex justify-content-between align-items-center gap-2">
  <a href="<?= url('/patrimonio') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar ao patrimônio</a>
  <button type="button" class="btn btn-sm btn-primary" data-print><i class="bi bi-printer"></i> Imprimir</button>
</div>
<main class="container-fluid py-2">
  <?= $content ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script src="<?= asset('js/labels.js') ?>"></script>
</body>
</html>
