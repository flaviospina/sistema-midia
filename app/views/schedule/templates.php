<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Modelos de escala</h1>
  <a href="<?= url('/modelos/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo modelo</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 table-responsive-stack">
  <thead class="table-light"><tr><th>Nome</th><th>Vagas</th><th>Situação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $t): ?>
    <tr class="<?= $t['active'] ? '' : 'text-muted' ?>">
      <td data-label="Nome"><span class="fw-semibold"><?= e($t['name']) ?></span><?php if ($t['description']): ?><div class="small text-muted"><?= e($t['description']) ?></div><?php endif; ?></td>
      <td data-label="Vagas"><?= e($t['summary'] ?? '—') ?></td>
      <td data-label="Situação"><?= $t['active'] ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td>
      <td class="text-end"><a href="<?= url('/modelos/' . (int) $t['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
