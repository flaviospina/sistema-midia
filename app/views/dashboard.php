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
<?php if ($stats['lgpd']): ?>
  <div class="alert alert-info"><i class="bi bi-shield-check"></i> Há <strong><?= $stats['lgpd'] ?></strong> solicitação(ões) de privacidade aguardando resposta. <a href="<?= url('/privacidade') ?>" class="alert-link">Ver fila</a></div>
<?php endif; ?>
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
