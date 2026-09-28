<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Olá, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
    <div class="text-muted small"><?= e(Auth::roleLabel($user['role'])) ?></div>
  </div>
</div>

<?php if (isset($stats)): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small">Equipe ativa</div>
      <div class="stat-value"><?= $stats['equipe'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small">Em treinamento</div>
      <div class="stat-value"><?= $stats['treino'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small">Usuários ativos</div>
      <div class="stat-value"><?= $stats['usuarios'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <a class="card stat-card h-100 text-decoration-none<?= $stats['pendentes'] ? ' border border-warning' : '' ?>" href="<?= url('/usuarios/pendentes') ?>"><div class="card-body">
      <div class="text-muted small">Cadastros pendentes</div>
      <div class="stat-value <?= $stats['pendentes'] ? 'text-warning' : '' ?>"><?= $stats['pendentes'] ?></div>
    </div></a>
  </div>
</div>
<?php if (!empty($openSlots)): ?>
  <div class="alert alert-warning"><i class="bi bi-people"></i> <strong><?= count($openSlots) ?></strong> evento(s) nos próximos 14 dias com vagas abertas ou recusas. <a href="<?= url('/escala/painel') ?>" class="alert-link">Painel da escala</a><?= !empty($pendingSwaps) ? ' · ' . $pendingSwaps . ' troca(s) para aprovar' : '' ?></div>
<?php endif; ?>
<?php if (!empty($mySwaps)): ?>
  <div class="alert alert-info"><i class="bi bi-arrow-left-right"></i> Você tem <strong><?= count($mySwaps) ?></strong> pedido(s) de troca para responder. <a href="<?= url('/minha-escala') ?>" class="alert-link">Minha escala</a></div>
<?php endif; ?>
<?php if (!empty($art)): ?>
  <?php if ($art['minha_revisao']): ?><div class="alert alert-info py-2"><i class="bi bi-brush"></i> <strong><?= $art['minha_revisao'] ?></strong> arte(s) aguardando a sua revisão. <a href="<?= url('/artes', ['status' => 'revisao_solicitante', 'meus' => 1]) ?>" class="alert-link">Ver</a></div><?php endif; ?>
  <?php if (Auth::can('art.approve_media') && $art['aprov_midia']): ?><div class="alert alert-warning py-2"><i class="bi bi-brush"></i> <strong><?= $art['aprov_midia'] ?></strong> arte(s) aguardando aprovação da mídia<?= $art['sem_designer'] ? ' · ' . $art['sem_designer'] . ' pedido(s) sem designer' : '' ?><?= $art['atrasados'] ? ' · <strong>' . $art['atrasados'] . ' atrasado(s)</strong>' : '' ?>. <a href="<?= url('/artes/kanban') ?>" class="alert-link">Kanban</a></div><?php endif; ?>
  <?php if (Auth::can('art.approve_pastoral') && $art['aprov_pastoral']): ?><div class="alert alert-warning py-2"><i class="bi bi-person-badge"></i> <strong><?= $art['aprov_pastoral'] ?></strong> arte(s) aguardando aprovação pastoral. <a href="<?= url('/artes', ['status' => 'aprovacao_pastoral']) ?>" class="alert-link">Ver</a></div><?php endif; ?>
  <?php if (Auth::can('art.produce') && !Auth::can('art.approve_media') && $art['minha_producao']): ?><div class="alert alert-info py-2"><i class="bi bi-brush"></i> Você tem <strong><?= $art['minha_producao'] ?></strong> arte(s) em produção. <a href="<?= url('/artes/kanban') ?>" class="alert-link">Kanban</a></div><?php endif; ?>
<?php endif; ?>
<?php if (!empty($quarantine)): ?>
  <div class="alert alert-warning"><i class="bi bi-shield-check"></i> Há <strong><?= $quarantine ?></strong> arquivo(s) aguardando moderação. <a href="<?= url('/moderacao') ?>" class="alert-link">Abrir quarentena</a></div>
<?php endif; ?>
<?php if ($stats['lgpd']): ?>
  <div class="alert alert-info"><i class="bi bi-shield-check"></i> Há <strong><?= $stats['lgpd'] ?></strong> solicitação(ões) de privacidade aguardando resposta. <a href="<?= url('/privacidade') ?>" class="alert-link">Ver fila</a></div>
<?php endif; ?>
<?php endif; ?>

<?php if (isset($myAssignments)): $pend = array_filter($myAssignments, static fn($a) => $a['status'] === 'pendente' && $a['event_status'] === 'agendado'); ?>
<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100<?= $pend ? ' border-warning' : '' ?>">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Minha escala (30 dias)</span><a href="<?= url('/minha-escala') ?>" class="small">ver tudo</a></div>
      <?php if (!$myAssignments): ?><div class="card-body text-muted small">Nenhuma escala nos próximos 30 dias.</div><?php else: ?>
      <ul class="list-group list-group-flush small">
        <?php foreach (array_slice($myAssignments, 0, 6) as $a): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <span><strong><?= e(format_date($a['starts_at'], 'd/m')) ?> <?= e(substr($a['starts_at'], 11, 5)) ?></strong> <?= e($a['event_title']) ?> · <?= e($a['function_name']) ?></span>
            <span class="badge <?= $a['event_status'] === 'cancelado' ? 'text-bg-secondary' : ($a['status'] === 'confirmado' ? 'text-bg-success' : ($a['status'] === 'recusado' ? 'text-bg-secondary' : 'text-bg-warning')) ?>"><?= $a['event_status'] === 'cancelado' ? 'cancelado' : e(Assignment::STATUSES[$a['status']]) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($pend): ?><div class="card-footer bg-white"><a href="<?= url('/minha-escala') ?>" class="btn btn-sm btn-warning">Confirmar <?= count($pend) ?> escala(s)</a></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Próximos eventos</span><a href="<?= url('/eventos') ?>" class="small">calendário</a></div>
      <?php if (empty($upcoming)): ?><div class="card-body text-muted small">Nada nos próximos 7 dias.</div><?php else: ?>
      <ul class="list-group list-group-flush small">
        <?php foreach ($upcoming as $ev): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url('/eventos/' . (int) $ev['id']) ?>"><?= e($ev['title']) ?></a><span class="text-muted"><?= e(Event::WEEKDAYS[(int) date('w', strtotime($ev['starts_at']))]) ?> <?= e(format_date($ev['starts_at'], 'd/m H:i')) ?></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php elseif (!empty($upcoming)): ?>
  <div class="card mb-3"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Próximos eventos</span><a href="<?= url('/eventos') ?>" class="small">calendário</a></div>
    <ul class="list-group list-group-flush small"><?php foreach ($upcoming as $ev): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url('/eventos/' . (int) $ev['id']) ?>"><?= e($ev['title']) ?></a><span class="text-muted"><?= e(format_date($ev['starts_at'], 'd/m H:i')) ?></span></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (!empty($artMine) || !empty($pubs)): ?>
<div class="row g-3 mb-3">
  <?php if (!empty($artMine)): ?>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Meus pedidos de arte</span><a href="<?= url('/artes') ?>" class="small">todos</a></div>
    <ul class="list-group list-group-flush small"><?php foreach ($artMine as $r): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a href="<?= url('/artes/' . (int) $r['id']) ?>"><?= e($r['title']) ?></a><span><span class="text-muted me-1"><?= e(format_date($r['publish_on'], 'd/m')) ?></span><span class="badge text-bg-<?= ArtRequest::STATUS_COLORS[$r['status']] ?>"><?= e(ArtRequest::STATUSES[$r['status']]) ?></span></span></li><?php endforeach; ?></ul></div></div>
  <?php endif; ?>
  <?php if (!empty($pubs)): ?>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Publicações dos próximos 7 dias</span><a href="<?= url('/comunicacao') ?>" class="small">calendário</a></div>
    <ul class="list-group list-group-flush small"><?php foreach ($pubs as $p): ?><li class="list-group-item d-flex justify-content-between"><span><i class="bi <?= Publication::CHANNEL_ICONS[$p['channel']] ?>"></i> <?= e($p['title']) ?></span><span class="text-muted <?= $p['publish_at'] < date('Y-m-d H:i:s') ? 'text-danger' : '' ?>"><?= e(format_date($p['publish_at'], 'd/m H:i')) ?></span></li><?php endforeach; ?></ul></div></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if (!empty($recentFiles)): ?>
  <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 text-muted mb-0">Arquivos recentes</h2><a href="<?= url('/arquivos') ?>" class="small">Ver repositório</a></div>
  <div class="file-grid mb-4"><?php foreach ($recentFiles as $file) partial('file_card', ['file' => $file, 'selectable' => false]); ?></div>
<?php endif; ?>
<div class="row g-3">
  <?php if (!empty($byFunction)): ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Equipe por função</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Função</th><th class="text-center">Aprendiz</th><th class="text-center">Apto</th><th class="text-center">Referência</th></tr></thead>
          <tbody>
          <?php foreach ($byFunction as $f): ?>
            <tr>
              <td><?= e($f['name']) ?></td>
              <td class="text-center"><?= (int) $f['aprendiz'] ?></td>
              <td class="text-center"><?= (int) $f['apto'] ?></td>
              <td class="text-center"><?= (int) $f['referencia'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Meu cadastro</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <?php partial('avatar', ['u' => $user, 'size' => 64]) ?>
          <div>
            <div class="fw-semibold"><?= e($user['name']) ?></div>
            <div class="text-muted small"><?= e($user['email']) ?><?= $user['whatsapp'] ? ' · ' . e(format_phone($user['whatsapp'])) : '' ?></div>
          </div>
        </div>
        <?php if (isset($myFunctions)): ?>
          <div class="small text-muted mb-1">Minhas funções</div>
          <?php if ($myFunctions): foreach ($myFunctions as $f): ?>
            <span class="badge badge-level-<?= e($f['level']) ?> me-1 mb-1"><?= e($f['name']) ?> · <?= e(MediaFunction::LEVELS[$f['level']]) ?></span>
          <?php endforeach; else: ?>
            <div class="text-muted small mb-2">Nenhuma função atribuída ainda.</div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($myMinistries): ?>
          <div class="small text-muted mt-2 mb-1">Ministérios</div>
          <?php foreach ($myMinistries as $m): ?>
            <span class="badge text-bg-light border me-1 mb-1"><?= e($m['name']) ?><?= $m['is_leader'] ? ' (líder)' : '' ?></span>
          <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-3"><a href="<?= url('/meus-dados') ?>" class="btn btn-sm btn-outline-primary">Meus dados e privacidade</a></div>
      </div>
    </div>
  </div>

  <?php if (!empty($recentAudit)): ?>
  <div class="col-12">
    <div class="card">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>Últimas ações</span><a href="<?= url('/auditoria') ?>" class="small">Ver auditoria</a>
      </div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($recentAudit as $a): ?>
          <li class="list-group-item d-flex justify-content-between gap-2">
            <span><strong><?= e($a['user_name'] ?? 'sistema') ?></strong> · <?= e($a['action']) ?> <span class="text-muted"><?= e($a['entity']) ?><?= $a['entity_id'] !== null ? ' #' . e($a['entity_id']) : '' ?></span></span>
            <span class="text-muted text-nowrap"><?= e(format_datetime($a['created_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>
</div>
