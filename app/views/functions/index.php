<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Funções da equipe</h1>
  <a href="<?= url('/funcoes/novo') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova função</a>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 table-responsive-stack">
      <thead class="table-light"><tr><th>Ordem</th><th>Função</th><th class="text-center">Pessoas</th><th>Situação</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($functions as $f): ?>
        <tr>
          <td data-label="Ordem"><?= (int) $f['sort_order'] ?></td>
          <td data-label="Função"><span class="fw-semibold"><?= e($f['name']) ?></span> <span class="text-muted small">(<?= e($f['slug']) ?>)</span><?php if ($f['description']): ?><div class="small text-muted"><?= e($f['description']) ?></div><?php endif; ?></td>
          <td data-label="Pessoas" class="text-center"><?= (int) $f['members_count'] ?></td>
          <td data-label="Situação"><?= $f['active'] ? '<span class="badge text-bg-success">Ativa</span>' : '<span class="badge text-bg-secondary">Inativa</span>' ?></td>
          <td class="text-end"><a href="<?= url('/funcoes/' . (int) $f['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white small text-muted">Funções não podem ser excluídas (preservam o histórico); desative as que não usar mais.</div>
</div>
