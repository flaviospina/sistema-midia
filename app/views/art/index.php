<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Pedidos de arte</h1>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (Auth::can('art.produce')): ?><a href="<?= url('/artes/kanban') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-kanban"></i> Kanban</a><?php endif; ?>
    <?php if (Auth::can('art.manage')): ?><a href="<?= url('/artes/atrasos') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-alarm"></i> Atrasos</a><?php endif; ?>
    <?php if (Auth::can('art.request')): ?><a href="<?= url('/artes/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo pedido</a><?php endif; ?>
  </div>
</div>
<form class="card mb-3" method="get" action="<?= url('/artes') ?>"><div class="card-body row g-2">
  <div class="col-12 col-md-3"><input type="search" name="q" class="form-control form-control-sm" value="<?= e($filters['q']) ?>" placeholder="Título ou briefing"></div>
  <div class="col-6 col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Em andamento</option><?php foreach (ArtRequest::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><select name="ministerio" class="form-select form-select-sm"><option value="">Todos os ministérios</option><?php foreach ($ministries as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], $filters['ministry_id']) ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
  <?php if ($designers): ?><div class="col-6 col-md-2"><select name="designer" class="form-select form-select-sm"><option value="">Qualquer designer</option><?php foreach ($designers as $d): ?><option value="<?= (int) $d['id'] ?>"<?= selected($d['id'], $filters['designer_id']) ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="col-6 col-md-3 d-flex gap-2 align-items-center">
    <div class="form-check form-check-inline small"><input class="form-check-input" type="checkbox" name="meus" value="1" id="meus"<?= checked($filters['mine']) ?>><label class="form-check-label" for="meus">Só os meus</label></div>
    <div class="form-check form-check-inline small"><input class="form-check-input" type="checkbox" name="todos" value="1" id="todos"<?= checked($filters['all']) ?>><label class="form-check-label" for="todos">Incluir concluídos</label></div>
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
  </div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 table-responsive-stack">
  <thead class="table-light"><tr><th>Pedido</th><th>Ministério</th><th>Formatos</th><th>Publicação</th><th>Designer</th><th>Situação</th></tr></thead>
  <tbody>
  <?php if (!$result['itens']): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum pedido.</td></tr><?php endif; ?>
  <?php foreach ($result['itens'] as $r): $late = $r['publish_on'] < date('Y-m-d') && !in_array($r['status'], ['aprovado', 'publicado', 'cancelado'], true); ?>
    <tr>
      <td data-label="Pedido"><a href="<?= url('/artes/' . (int) $r['id']) ?>" class="fw-semibold"><?= e($r['title']) ?></a><?= $r['is_urgent'] ? ' <span class="badge text-bg-danger" title="Prazo menor que o mínimo">urgente</span>' : '' ?><br><span class="small text-muted">#<?= (int) $r['id'] ?> · <?= e($r['requester_name']) ?> · <?= e(format_date($r['created_at'])) ?></span></td>
      <td data-label="Ministério"><?= e($r['ministry_name'] ?? '—') ?></td>
      <td data-label="Formatos"><?php foreach (ArtRequest::formatsOf($r) as $f): ?><span class="badge text-bg-light border"><?= e(explode(' ', ArtRequest::FORMATS[$f] ?? $f)[0]) ?></span> <?php endforeach; ?><?= $r['needs_pastoral'] ? '<span class="badge text-bg-light border" title="Exige aprovação pastoral"><i class="bi bi-person-badge"></i></span>' : '' ?></td>
      <td data-label="Publicação" class="<?= $late ? 'text-danger fw-bold' : '' ?>"><?= e(format_date($r['publish_on'])) ?></td>
      <td data-label="Designer"><?= e($r['designer_name'] ?? '—') ?></td>
      <td data-label="Situação"><span class="badge text-bg-<?= ArtRequest::STATUS_COLORS[$r['status']] ?>"><?= e(ArtRequest::STATUSES[$r['status']]) ?></span><?= $r['versions_count'] ? ' <span class="small text-muted">v' . (int) $r['current_version'] . '</span>' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php partial('pagination', ['result' => $result]) ?>
