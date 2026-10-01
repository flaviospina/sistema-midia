<?php
/** @var string $content */
$me = Auth::user();
// Caminho atual (sem a subpasta de publicação) para marcar o item ativo do menu
$currentPath = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(BASE_PATH)), '/');
$isActive = static fn(string $path): bool => $path === '/' ? $currentPath === '/' : ($currentPath === $path || str_starts_with($currentPath, $path . '/'));
$navItem = static function (string $path, string $icon, string $label, int $badge = 0) use ($isActive): string {
    return '<li class="nav-item"><a class="nav-link' . ($isActive($path) ? ' active" aria-current="page' : '') . '" href="' . url($path) . '"><i class="bi ' . $icon . '"></i><span>' . e($label) . '</span>'
        . ($badge ? '<span class="badge text-bg-warning ms-auto">' . $badge . '</span>' : '') . '</a></li>';
};
$subItem = static fn(string $path, string $label): string => '<li><a class="nav-link sub' . ($isActive($path) ? ' active" aria-current="page' : '') . '" href="' . url($path) . '">' . e($label) . '</a></li>';
?>
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
<body class="<?= $me ? 'com-menu' : '' ?>">
<?php if ($me): ?>
<!-- Barra superior: só no celular/tablet (abre o menu lateral) -->
<header class="topo topo--mobile d-lg-none d-flex align-items-center gap-2 px-3 sticky-top">
  <button class="btn btn-link text-white p-1 fs-4 lh-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu" aria-label="Abrir menu"><i class="bi bi-list"></i></button>
  <a class="navbar-brand marca d-flex align-items-center gap-2 me-auto" href="<?= url('/') ?>">
    <span class="marca__logo"><i class="bi bi-camera-reels"></i></span> <span>Central de <strong>Mídia</strong></span>
  </a>
  <a href="<?= url('/meus-dados') ?>" class="d-flex" aria-label="Meus dados"><?php partial('avatar', ['u' => $me, 'size' => 28]) ?></a>
</header>

<!-- Menu lateral: fixo no desktop, gaveta (offcanvas) no celular -->
<nav class="menu-lateral offcanvas-lg offcanvas-start" tabindex="-1" id="menu" aria-label="Menu principal">
  <div class="menu-lateral__topo">
    <a class="marca d-flex align-items-center gap-2" href="<?= url('/') ?>">
      <span class="marca__logo"><i class="bi bi-camera-reels"></i></span>
      <span class="marca__texto">Central de <strong>Mídia</strong><small>ADMoema</small></span>
    </a>
    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menu" aria-label="Fechar"></button>
  </div>

  <div class="menu-lateral__corpo">
    <ul class="nav flex-column">
      <?= $navItem('/', 'bi-house', 'Início') ?>
      <?php if (Auth::can('events.view')) echo $navItem('/eventos', 'bi-calendar3', 'Eventos'); ?>
      <?php if (Auth::can('schedule.self')) echo $navItem('/minha-escala', 'bi-person-check', 'Minha escala', count(Assignment::pendingForUser((int) $me['id']))); ?>
      <?php if (Auth::can('schedule.self')) echo $navItem('/indisponibilidades', 'bi-calendar-x', 'Indisponibilidades'); ?>
      <?php if (Auth::can('files.browse')) echo $navItem('/arquivos', 'bi-hdd-stack', 'Arquivos'); ?>
      <?php if (Auth::can('art.request')): $ac = ArtRequest::countsForDashboard(); $an = $ac['minha_revisao'] + (Auth::can('art.approve_pastoral') && !Auth::is('admin') ? $ac['aprov_pastoral'] : 0) + (Auth::can('art.approve_media') ? $ac['aprov_midia'] : 0); echo $navItem('/artes', 'bi-brush', 'Artes', $an); endif; ?>
      <?php if (Auth::can('publications.view')) echo $navItem('/comunicacao', 'bi-megaphone', 'Comunicação'); ?>
      <?php if (Auth::can('files.moderate')) echo $navItem('/moderacao', 'bi-shield-check', 'Quarentena', MediaFile::countQuarantine()); ?>
    </ul>

    <?php if (Auth::can('equipment.view') || Auth::can('training.view') || Auth::can('incidents.report') || Auth::can('leader.dashboard')): ?>
      <div class="menu-lateral__titulo">Equipe</div>
      <ul class="nav flex-column">
        <?php if (Auth::can('equipment.view')) echo $navItem('/patrimonio', 'bi-box-seam', 'Patrimônio'); ?>
        <?php if (Auth::can('incidents.report')) echo $navItem('/ocorrencias', 'bi-exclamation-triangle', 'Ocorrências'); ?>
        <?php if (Auth::can('training.view')) echo $navItem('/capacitacao', 'bi-mortarboard', 'Capacitação'); ?>
        <?php if (Auth::can('leader.dashboard')) echo $navItem('/painel-lider', 'bi-speedometer2', 'Painel do líder'); ?>
      </ul>
    <?php endif; ?>

    <?php if (Auth::can('users.view') || Auth::can('ministries.view') || Auth::can('restrictions.view')): ?>
      <div class="menu-lateral__titulo">Cadastros</div>
      <ul class="nav flex-column">
        <?php if (Auth::can('users.view')) echo $navItem('/usuarios', 'bi-people', 'Pessoas'); ?>
        <?php if (Auth::can('ministries.view')) echo $navItem('/ministerios', 'bi-diagram-3', 'Ministérios'); ?>
        <?php if (Auth::can('restrictions.view')) echo $navItem('/restricoes', 'bi-eye-slash', 'Restrições de imagem'); ?>
      </ul>
    <?php endif; ?>

    <?php if (Auth::is('admin') || Auth::can('users.approve')): $isAdmin = Auth::is('admin');
      $groups = [
        ['escala', 'bi-calendar-week', 'Escala', [['/escala/painel', 'Painel da escala'], ['/recorrencias', 'Cultos fixos'], ['/modelos', 'Modelos de escala'], ['/trocas', 'Pedidos de troca'], ['/indisponibilidades/equipe', 'Indisponibilidades da equipe'], ['/checklist', 'Checklist pré-culto (itens)']]],
        ['artes', 'bi-palette', 'Artes', [['/artes/atrasos', 'Atrasos e prazos'], ['/artes/checklist', 'Checklist de identidade']]],
        ['capacitacao', 'bi-mortarboard', 'Capacitação', [['/capacitacao/trilhas', 'Trilhas de capacitação'], ['/capacitacao/equipe', 'Capacitação da equipe']]],
        ['repositorio', 'bi-folder2-open', 'Repositório', [['/compartilhamentos', 'Links de compartilhamento'], ['/armazenamento', 'Armazenamento'], ['/arquivos/lixeira', 'Lixeira']]],
        ['pessoas', 'bi-person-gear', 'Pessoas', array_merge([['/usuarios/pendentes', 'Cadastros pendentes']], $isAdmin ? [['/funcoes', 'Funções da equipe'], ['/privacidade', 'Privacidade (LGPD)']] : [])],
      ];
      if ($isAdmin) { $groups[] = ['sistema', 'bi-gear', 'Sistema', [['/integracoes', 'Integrações (n8n / WhatsApp)'], ['/auditoria', 'Auditoria']]]; }
    ?>
      <div class="menu-lateral__titulo"><?= $isAdmin ? 'Administração' : 'Coordenação' ?></div>
      <ul class="nav flex-column">
        <?php foreach ($groups as [$key, $icon, $label, $items]): $temAtivo = (bool) array_filter($items, static fn($i) => $isActive($i[0])); ?>
          <li class="nav-item">
            <a class="nav-link grupo<?= $temAtivo ? ' pai-ativo' : '' ?>" href="#grupo-<?= $key ?>" role="button" data-submenu aria-expanded="false" aria-controls="grupo-<?= $key ?>"><i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span><i class="bi bi-chevron-down seta ms-auto"></i></a>
            <div class="submenu" id="grupo-<?= $key ?>"><ul class="nav flex-column">
              <?php foreach ($items as [$p, $l]) echo $subItem($p, $l); ?>
            </ul></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="menu-lateral__rodape">
    <a href="<?= url('/meus-dados') ?>" class="usuario d-flex align-items-center gap-2">
      <?php partial('avatar', ['u' => $me, 'size' => 40]) ?>
      <span class="min-w-0"><strong class="d-block text-truncate"><?= e($me['name']) ?></strong><small><?= e(Auth::roleLabel($me['role'])) ?></small></span>
    </a>
    <div class="d-flex gap-1 mt-2">
      <a href="<?= url('/meus-dados') ?>" class="btn btn-sm btn-outline-light flex-fill" title="Meus dados"><i class="bi bi-person-badge"></i> Dados</a>
      <a href="<?= url('/trocar-senha') ?>" class="btn btn-sm btn-outline-light flex-fill" title="Trocar senha"><i class="bi bi-key"></i> Senha</a>
      <form method="post" action="<?= url('/sair') ?>" class="flex-fill"><?= Csrf::field() ?>
        <button class="btn btn-sm btn-outline-light w-100" title="Sair"><i class="bi bi-box-arrow-right"></i> Sair</button>
      </form>
    </div>
  </div>
</nav>
<?php endif; ?>

<div class="conteudo">
  <main class="container-fluid container-xl py-3 py-md-4">
    <?php partial('flashes') ?>
    <?= $content ?>
  </main>
  <footer class="text-center text-muted small py-3">
    <?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?> · <a href="<?= url('/termo-de-uso') ?>" class="text-muted">Termo de uso e privacidade</a>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/files.js') ?>"></script>
<script src="<?= asset('js/uploader.js') ?>"></script>
</body>
</html>
