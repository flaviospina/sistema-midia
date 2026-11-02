<?php /** @var string $content  Layout das páginas públicas de compartilhamento (sem login, com metatags sociais) */ ?>
<!DOCTYPE html>
<html lang="pt-BR" prefix="og: https://ogp.me/ns#">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?php partial('meta', ['meta' => $meta ?? null, 'title' => $title ?? '']) ?>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= asset('img/icone-180.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/fotos.css') ?>" rel="stylesheet">
</head>
<body class="bg-auth">
<header class="publico-topo">
  <a class="marca d-flex align-items-center gap-2" href="<?= url('/') ?>"><span class="marca__logo"><i class="bi bi-camera"></i></span><span class="marca__texto">Envio de <strong>Mídias</strong><small>AD Ministério do Belém · Setor 124 Moema</small></span></a>
</header>
<main class="container py-3 py-md-4">
  <?= $content ?>
</main>
<footer class="text-center small py-3 publico-rodape"><?= e(APP_NAME) ?> · <a href="<?= url('/login') ?>">Entrar</a></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/share.js') ?>"></script>
</body>
</html>
