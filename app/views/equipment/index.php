<?php $canManage = Auth::can('equipment.manage'); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Patrimônio</h1>
    <div class="text-muted small"><?= (int) $summary['total'] ?> item(ns) em uso · valor estimado R$ <?= number_format($summary['valor'] / 100, 2, ',', '.') ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canManage): ?>
      <button type="submit" form="labelsForm" class="btn btn-sm btn-outline-secondary" id="labelsBtn" disabled><i class="bi bi-qr-code"></i> Etiquetas selecionadas</button>
      <a href="<?= url('/patrimonio/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo item</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($mine): ?>
  <div class="alert alert-info py-2"><i class="bi bi-box-seam"></i> Você está com:
    <?php foreach ($mine as $l): ?><a href="<?= url('/patrimonio/' . (int) $l['equipment_id']) ?>" class="alert-link"><?= e($l['code']) ?> <?= e($l['equipment_name']) ?></a><?= $l['due_on'] ? ' (até ' . e(format_date($l['due_on'])) . ')' : '' ?>; <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($overdue): ?>
  <div class="alert alert-warning py-2"><i class="bi bi-alarm"></i> <strong><?= count($overdue) ?></strong> empréstimo(s) atrasado(s):
    <?php foreach ($overdue as $l): ?><a href="<?= url('/patrimonio/' . (int) $l['equipment_id']) ?>" class="alert-link"><?= e($l['code']) ?></a> com <?= e($l['user_name']) ?> (venceu <?= e(format_date($l['due_on'])) ?>)<?= $l['whatsapp'] ? ' <a href="' . e(whatsapp_link($l['whatsapp'])) . '" target="_blank" rel="noopener" title="Cobrar pelo WhatsApp"><i class="bi bi-whatsapp"></i></a>' : '' ?>; <?php endforeach; ?>
  </div>
<?php endif; ?>

<form class="card mb-3" method="get" action="<?= url('/patrimonio') ?>"><div class="card-body row g-2">
  <div class="col-12 col-md-4"><input type="search" name="q" class="form-control form-control-sm" value="<?= e($filters['q']) ?>" placeholder="Código, nome, marca, série, local"></div>
  <div class="col-6 col-md-3"><select name="categoria" class="form-select form-select-sm"><option value="">Todas as categorias</option><?php foreach (Equipment::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['category']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-3"><select name="status" class="form-select form-select-sm"><option value="">Em uso (sem baixados)</option><?php foreach (Equipment::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-12 col-md-2 d-flex gap-2 align-items-center">
    <div class="form-check form-check-inline small"><input class="form-check-input" type="checkbox" name="todos" value="1" id="todos"<?= checked($filters['all']) ?>><label class="form-check-label" for="todos">Incluir baixados</label></div>
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
  </div>
</div></form>

<form id="labelsForm" method="post" action="<?= url('/patrimonio/etiquetas') ?>" target="_blank"><?= Csrf::field() ?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 table-responsive-stack">
  <thead class="table-light"><tr>
    <?php if ($canManage): ?><th class="w-1"><input type="checkbox" class="form-check-input" data-check-all="labelsForm" aria-label="Selecionar todos"></th><?php endif; ?>
    <th>Código</th><th>Item</th><th>Categoria</th><th>Local</th><th>Situação</th>
  </tr></thead>
  <tbody>
  <?php if (!$result['itens']): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum item.</td></tr><?php endif; ?>
  <?php foreach ($result['itens'] as $q): ?>
    <tr>
      <?php if ($canManage): ?><td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= (int) $q['id'] ?>" aria-label="Selecionar <?= e($q['code']) ?>"></td><?php endif; ?>
      <td data-label="Código"><a href="<?= url('/patrimonio/' . (int) $q['id']) ?>" class="fw-semibold font-monospace"><?= e($q['code']) ?></a></td>
      <td data-label="Item"><?= e($q['name']) ?><?= $q['brand'] || $q['model'] ? '<br><span class="small text-muted">' . e(trim($q['brand'] . ' ' . $q['model'])) . '</span>' : '' ?></td>
      <td data-label="Categoria"><?= e(Equipment::CATEGORIES[$q['category']] ?? $q['category']) ?></td>
      <td data-label="Local"><?= e($q['location'] ?? '—') ?></td>
      <td data-label="Situação"><span class="badge text-bg-<?= Equipment::STATUS_COLORS[$q['status']] ?>"><?= e(Equipment::STATUSES[$q['status']]) ?></span>
        <?php if ($q['status'] === 'emprestado' && $q['loan_user_name']): ?><br><span class="small text-muted">com <?= e($q['loan_user_name']) ?><?= $q['loan_due_on'] ? ' até ' . e(format_date($q['loan_due_on'])) : '' ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
</form>
<?php partial('pagination', ['result' => $result]) ?>
<?php if ($summary['por_categoria']): ?>
  <div class="mt-3 small text-muted">Por categoria: <?php foreach ($summary['por_categoria'] as $c => $n): ?><span class="badge text-bg-light border"><?= e(Equipment::CATEGORIES[$c] ?? $c) ?>: <?= $n ?></span> <?php endforeach; ?></div>
<?php endif; ?>
