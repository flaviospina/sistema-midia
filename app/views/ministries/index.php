<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Ministérios</h1>
  <?php if (Auth::can('ministries.manage')): ?>
    <a href="<?= url('/ministerios/novo') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo ministério</a>
  <?php endif; ?>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 table-responsive-stack">
      <thead class="table-light"><tr><th>Nome</th><th>Líder(es)</th><th class="text-center">Pessoas</th><th>Situação</th><?php if (Auth::can('ministries.manage')): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (!$ministries): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhum ministério cadastrado.</td></tr><?php endif; ?>
      <?php foreach ($ministries as $m): ?>
        <tr>
          <td data-label="Nome"><span class="fw-semibold"><?= e($m['name']) ?></span><?php if ($m['description']): ?><div class="small text-muted"><?= e($m['description']) ?></div><?php endif; ?></td>
          <td data-label="Líder"><?= e($m['leaders'] ?: '—') ?></td>
          <td data-label="Pessoas" class="text-center"><?= (int) $m['members_count'] ?></td>
          <td data-label="Situação"><?= $m['active'] ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td>
          <?php if (Auth::can('ministries.manage')): ?>
            <td class="text-end"><a href="<?= url('/ministerios/' . (int) $m['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
