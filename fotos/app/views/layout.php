<?php
/** @var string $content */
$me = Auth::user();
$currentPath = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(BASE_PATH)), '/');
$isActive = static fn(string $p): bool => $p === '/' ? ($currentPath === '/' || str_starts_with($currentPath, '/albuns')) : ($currentPath === $p || str_starts_with($currentPath, $p . '/'));
$navItem = static fn(string $path, string $icon, string $label): string => '<li class="nav-item"><a class="nav-link' . ($isActive($path) ? ' active" aria-current="page' : '') . '" href="' . url($path) . '"><i class="bi ' . $icon . '"></i><span>' . e($label) . '</span></a></li>';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
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
<body class="<?= $me ? 'com-menu' : '' ?>">
<?php if ($me): ?>
<header class="topo topo--mobile d-lg-none d-flex align-items-center gap-2 px-3 sticky-top">
  <button class="btn btn-link text-white p-1 fs-4 lh-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu" aria-label="Abrir menu"><i class="bi bi-list"></i></button>
  <a class="marca d-flex align-items-center gap-2 me-auto" href="<?= url('/') ?>"><span class="marca__logo"><i class="bi bi-camera"></i></span> <span>Fotos <strong>ADMoema</strong></span></a>
  <a href="<?= url('/meus-dados') ?>" class="d-flex" aria-label="Meus dados"><?php partial('avatar', ['u' => $me, 'size' => 28]) ?></a>
</header>
<nav class="menu-lateral offcanvas-lg offcanvas-start" tabindex="-1" id="menu" aria-label="Menu principal">
  <div class="menu-lateral__topo">
    <a class="marca d-flex align-items-center gap-2" href="<?= url('/') ?>"><span class="marca__logo"><i class="bi bi-camera"></i></span><span class="marca__texto">Fotos <strong>ADMoema</strong><small>Cultos e eventos</small></span></a>
    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menu" aria-label="Fechar"></button>
  </div>
  <div class="menu-lateral__corpo">
    <ul class="nav flex-column">
      <?= $navItem('/', 'bi-images', 'Álbuns') ?>
      <?php if (Auth::can('albums.manage')) echo $navItem('/albuns/novo', 'bi-folder-plus', 'Nova pasta / enviar fotos'); ?>
      <?= $navItem('/meus-dados', 'bi-person-badge', 'Meus dados' . (Auth::can('social.share') ? ' e redes sociais' : '')) ?>
    </ul>
    <?php if (Auth::is('admin')): ?>
      <div class="menu-lateral__titulo">Administração</div>
      <ul class="nav flex-column">
        <?= $navItem('/admin/usuarios', 'bi-people', 'Usuários') ?>
        <?= $navItem('/admin/auditoria', 'bi-journal-text', 'Auditoria') ?>
      </ul>
    <?php endif; ?>
  </div>
  <div class="menu-lateral__rodape">
    <a href="<?= url('/meus-dados') ?>" class="usuario d-flex align-items-center gap-2"><?php partial('avatar', ['u' => $me, 'size' => 40]) ?><span class="min-w-0"><strong class="d-block text-truncate"><?= e($me['name']) ?></strong><small><?= e(Auth::roleLabel($me['role'])) ?></small></span></a>
    <div class="d-flex gap-1 mt-2">
      <a href="<?= url('/trocar-senha') ?>" class="btn btn-sm btn-outline-light flex-fill"><i class="bi bi-key"></i> Senha</a>
      <form method="post" action="<?= url('/sair') ?>" class="flex-fill"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right"></i> Sair</button></form>
    </div>
  </div>
</nav>
<?php endif; ?>
<div class="conteudo">
  <main class="container-fluid container-xxl py-3 py-md-4">
    <?php partial('flashes') ?>
    <?= $content ?>
  </main>
  <footer class="text-center text-muted small py-3"><?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?> · uso interno da igreja</footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?><script src="<?= asset('js/' . $s) ?>"></script><?php endforeach; ?>
</body>
</html>
