<?php /** @var string $content */ $me = Auth::user(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<?php if ($me): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('/') ?>">
      <i class="bi bi-camera-reels"></i> <span>Central de Mídia</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= url('/') ?>"><i class="bi bi-house"></i> Início</a></li>
        <?php if (Auth::can('events.view')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/eventos') ?>"><i class="bi bi-calendar3"></i> Eventos</a></li>
        <?php endif; ?>
        <?php if (Auth::can('schedule.self')): $pn = count(Assignment::pendingForUser((int) $me['id'])); ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/minha-escala') ?>"><i class="bi bi-person-check"></i> Minha escala<?= $pn ? ' <span class="badge text-bg-warning">' . $pn . '</span>' : '' ?></a></li>
        <?php endif; ?>
        <?php if (Auth::can('files.browse')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/arquivos') ?>"><i class="bi bi-hdd-stack"></i> Arquivos</a></li>
        <?php endif; ?>
        <?php if (Auth::can('files.moderate')): $qn = MediaFile::countQuarantine(); ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/moderacao') ?>"><i class="bi bi-shield-check"></i> Quarentena<?= $qn ? ' <span class="badge text-bg-warning">' . $qn . '</span>' : '' ?></a></li>
        <?php endif; ?>
        <?php if (Auth::can('users.view')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/usuarios') ?>"><i class="bi bi-people"></i> Pessoas</a></li>
        <?php endif; ?>
        <?php if (Auth::can('ministries.view')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/ministerios') ?>"><i class="bi bi-diagram-3"></i> Ministérios</a></li>
        <?php endif; ?>
        <?php if (Auth::is('admin')): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Administração</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('/escala/painel') ?>">Painel da escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/recorrencias') ?>">Cultos fixos</a></li>
              <li><a class="dropdown-item" href="<?= url('/modelos') ?>">Modelos de escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/trocas') ?>">Pedidos de troca</a></li>
              <li><a class="dropdown-item" href="<?= url('/indisponibilidades/equipe') ?>">Indisponibilidades da equipe</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/restricoes') ?>">Restrições de imagem</a></li>
              <li><a class="dropdown-item" href="<?= url('/compartilhamentos') ?>">Links de compartilhamento</a></li>
              <li><a class="dropdown-item" href="<?= url('/armazenamento') ?>">Armazenamento</a></li>
              <li><a class="dropdown-item" href="<?= url('/arquivos/lixeira') ?>">Lixeira</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/funcoes') ?>">Funções da equipe</a></li>
              <li><a class="dropdown-item" href="<?= url('/usuarios/pendentes') ?>">Cadastros pendentes</a></li>
              <li><a class="dropdown-item" href="<?= url('/privacidade') ?>">Privacidade (LGPD)</a></li>
              <li><a class="dropdown-item" href="<?= url('/auditoria') ?>">Auditoria</a></li>
            </ul>
          </li>
        <?php elseif (Auth::can('users.approve')): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Coordenação</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('/escala/painel') ?>">Painel da escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/recorrencias') ?>">Cultos fixos</a></li>
              <li><a class="dropdown-item" href="<?= url('/modelos') ?>">Modelos de escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/trocas') ?>">Pedidos de troca</a></li>
              <li><a class="dropdown-item" href="<?= url('/indisponibilidades/equipe') ?>">Indisponibilidades da equipe</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/usuarios/pendentes') ?>">Cadastros pendentes</a></li>
              <li><a class="dropdown-item" href="<?= url('/restricoes') ?>">Restrições de imagem</a></li>
              <li><a class="dropdown-item" href="<?= url('/compartilhamentos') ?>">Links de compartilhamento</a></li>
              <li><a class="dropdown-item" href="<?= url('/armazenamento') ?>">Armazenamento</a></li>
              <li><a class="dropdown-item" href="<?= url('/arquivos/lixeira') ?>">Lixeira</a></li>
            </ul>
          </li>
        <?php elseif (Auth::can('restrictions.view')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/restricoes') ?>"><i class="bi bi-eye-slash"></i> Restrições</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
            <?php partial('avatar', ['u' => $me, 'size' => 28]) ?>
            <span><?= e(explode(' ', $me['name'])[0]) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e(Auth::roleLabel($me['role'])) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('/meus-dados') ?>"><i class="bi bi-person-badge"></i> Meus dados</a></li>
            <li><a class="dropdown-item" href="<?= url('/trocar-senha') ?>"><i class="bi bi-key"></i> Trocar senha</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="post" action="<?= url('/sair') ?>"><?= Csrf::field() ?>
                <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Sair</button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>

<main class="container-fluid container-xl py-3 py-md-4">
  <?php partial('flashes') ?>
  <?= $content ?>
</main>

<footer class="text-center text-muted small py-3">
  <?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?> · <a href="<?= url('/termo-de-uso') ?>" class="text-muted">Termo de uso e privacidade</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/files.js') ?>"></script>
<script src="<?= asset('js/uploader.js') ?>"></script>
</body>
</html>
