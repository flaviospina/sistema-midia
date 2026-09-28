<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Privacidade (LGPD)</h1>
  <div class="btn-group btn-group-sm">
    <a href="<?= url('/privacidade') ?>" class="btn btn-outline-primary<?= $status === '' ? ' active' : '' ?>">Todas</a>
    <?php foreach (DataRequest::STATUSES as $k => $label): ?>
      <a href="<?= url('/privacidade', ['status' => $k]) ?>" class="btn btn-outline-primary<?= $status === $k ? ' active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php if (!$requests): ?>
  <div class="card"><div class="card-body text-center text-muted py-4">Nenhuma solicitação.</div></div>
<?php endif; ?>
<?php foreach ($requests as $r): ?>
  <div class="card mb-2<?= $r['status'] === 'aberta' ? ' border-warning' : '' ?>">
    <div class="card-body">
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
          <span class="badge <?= $r['status'] === 'aberta' ? 'text-bg-warning' : ($r['status'] === 'concluida' ? 'text-bg-success' : 'text-bg-secondary') ?>"><?= e(DataRequest::STATUSES[$r['status']]) ?></span>
          <strong class="ms-1"><?= e(DataRequest::TYPES[$r['request_type']] ?? $r['request_type']) ?></strong>
          <div class="small mt-1">
            <?php if ($r['user_id']): ?><a href="<?= url('/usuarios/' . (int) $r['user_id']) ?>"><?= e($r['user_name']) ?></a> · <?= e($r['user_email']) ?><?php else: ?><span class="text-muted">titular removido</span><?php endif; ?>
            · aberta em <?= e(format_datetime($r['created_at'])) ?>
          </div>
          <?php if ($r['details']): ?><div class="small text-muted mt-1"><?= nl2br(e($r['details'])) ?></div><?php endif; ?>
          <?php if ($r['resolved_at']): ?><div class="small text-muted mt-1">Tratada por <?= e($r['resolver_name'] ?? '—') ?> em <?= e(format_datetime($r['resolved_at'])) ?><?= $r['resolution_notes'] ? ': ' . e($r['resolution_notes']) : '' ?></div><?php endif; ?>
        </div>
        <?php if ($r['status'] === 'aberta'): ?>
        <form method="post" action="<?= url('/privacidade/' . (int) $r['id'] . '/resolver') ?>" class="d-flex flex-column gap-2" data-confirm="<?= $r['request_type'] === 'exclusao' ? 'Concluir esta solicitação irá REMOVER os dados pessoais do titular de forma irreversível. Continuar?' : 'Registrar a resposta desta solicitação?' ?>">
          <?= Csrf::field() ?>
          <input type="text" name="notes" class="form-control form-control-sm" placeholder="Resposta ao titular (opcional)" maxlength="2000">
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-success" name="status" value="concluida"><i class="bi bi-check-lg"></i> Concluir<?= $r['request_type'] === 'exclusao' ? ' e remover dados' : '' ?></button>
            <button class="btn btn-sm btn-outline-secondary" name="status" value="recusada">Recusar</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
