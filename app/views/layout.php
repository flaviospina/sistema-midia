<?php /** @var string $content */ $me = Auth::user(); ?>
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
<body>
<?php if ($me): ?>
<nav class="navbar navbar-expand-lg navbar-dark topo sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand marca d-flex align-items-center gap-2" href="<?= url('/') ?>">
      <span class="marca__logo"><i class="bi bi-camera-reels"></i></span> <span>Central de <strong>Mídia</strong></span>
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
        <?php if (Auth::can('art.request')): $ac = ArtRequest::countsForDashboard(); $an = $ac['minha_revisao'] + (Auth::can('art.approve_pastoral') && !Auth::is('admin') ? $ac['aprov_pastoral'] : 0) + (Auth::can('art.approve_media') ? $ac['aprov_midia'] : 0); ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/artes') ?>"><i class="bi bi-brush"></i> Artes<?= $an ? ' <span class="badge text-bg-warning">' . $an . '</span>' : '' ?></a></li>
        <?php endif; ?>
        <?php if (Auth::can('publications.view')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/comunicacao') ?>"><i class="bi bi-megaphone"></i> Comunicação</a></li>
        <?php endif; ?>
        <?php if (Auth::can('equipment.view') || Auth::can('training.view')): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-tools"></i> Equipe</a>
            <ul class="dropdown-menu">
              <?php if (Auth::can('equipment.view')): ?><li><a class="dropdown-item" href="<?= url('/patrimonio') ?>"><i class="bi bi-box-seam"></i> Patrimônio</a></li><?php endif; ?>
              <?php if (Auth::can('incidents.report')): ?><li><a class="dropdown-item" href="<?= url('/ocorrencias') ?>"><i class="bi bi-exclamation-triangle"></i> Ocorrências</a></li><?php endif; ?>
              <?php if (Auth::can('training.view')): ?><li><a class="dropdown-item" href="<?= url('/capacitacao') ?>"><i class="bi bi-mortarboard"></i> Capacitação</a></li><?php endif; ?>
              <?php if (Auth::can('leader.dashboard')): ?><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="<?= url('/painel-lider') ?>"><i class="bi bi-speedometer2"></i> Painel do líder</a></li><?php endif; ?>
            </ul>
          </li>
        <?php elseif (Auth::can('leader.dashboard')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('/painel-lider') ?>"><i class="bi bi-speedometer2"></i> Painel</a></li>
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
              <li><a class="dropdown-item" href="<?= url('/artes/atrasos') ?>">Artes: atrasos e prazos</a></li>
              <li><a class="dropdown-item" href="<?= url('/artes/checklist') ?>">Artes: checklist de identidade</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/escala/painel') ?>">Painel da escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/recorrencias') ?>">Cultos fixos</a></li>
              <li><a class="dropdown-item" href="<?= url('/modelos') ?>">Modelos de escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/trocas') ?>">Pedidos de troca</a></li>
              <li><a class="dropdown-item" href="<?= url('/indisponibilidades/equipe') ?>">Indisponibilidades da equipe</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/checklist') ?>">Checklist pré-culto (itens)</a></li>
              <li><a class="dropdown-item" href="<?= url('/capacitacao/trilhas') ?>">Trilhas de capacitação</a></li>
              <li><a class="dropdown-item" href="<?= url('/capacitacao/equipe') ?>">Capacitação da equipe</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/restricoes') ?>">Restrições de imagem</a></li>
              <li><a class="dropdown-item" href="<?= url('/compartilhamentos') ?>">Links de compartilhamento</a></li>
              <li><a class="dropdown-item" href="<?= url('/armazenamento') ?>">Armazenamento</a></li>
              <li><a class="dropdown-item" href="<?= url('/arquivos/lixeira') ?>">Lixeira</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/funcoes') ?>">Funções da equipe</a></li>
              <li><a class="dropdown-item" href="<?= url('/usuarios/pendentes') ?>">Cadastros pendentes</a></li>
              <li><a class="dropdown-item" href="<?= url('/integracoes') ?>">Integrações (n8n / WhatsApp)</a></li>
              <li><a class="dropdown-item" href="<?= url('/privacidade') ?>">Privacidade (LGPD)</a></li>
              <li><a class="dropdown-item" href="<?= url('/auditoria') ?>">Auditoria</a></li>
            </ul>
          </li>
        <?php elseif (Auth::can('users.approve')): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Coordenação</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('/artes/atrasos') ?>">Artes: atrasos e prazos</a></li>
              <li><a class="dropdown-item" href="<?= url('/artes/checklist') ?>">Artes: checklist de identidade</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/escala/painel') ?>">Painel da escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/recorrencias') ?>">Cultos fixos</a></li>
              <li><a class="dropdown-item" href="<?= url('/modelos') ?>">Modelos de escala</a></li>
              <li><a class="dropdown-item" href="<?= url('/trocas') ?>">Pedidos de troca</a></li>
              <li><a class="dropdown-item" href="<?= url('/indisponibilidades/equipe') ?>">Indisponibilidades da equipe</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('/checklist') ?>">Checklist pré-culto (itens)</a></li>
              <li><a class="dropdown-item" href="<?= url('/capacitacao/trilhas') ?>">Trilhas de capacitação</a></li>
              <li><a class="dropdown-item" href="<?= url('/capacitacao/equipe') ?>">Capacitação da equipe</a></li>
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
