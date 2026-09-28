<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Ocorrências</h1>
  <a href="<?= url('/ocorrencias/nova') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Nova ocorrência</a>
</div>
<?php if ($pendingReports): ?>
  <div class="alert alert-info py-2"><i class="bi bi-journal-text"></i> Relatório pós-culto pendente:
    <?php foreach ($pendingReports as $ev): ?><a href="<?= url('/eventos/' . (int) $ev['id'] . '/relatorio') ?>" class="alert-link"><?= e($ev['title']) ?> (<?= e(format_date($ev['starts_at'], 'd/m')) ?>)</a>; <?php endforeach; ?>
  </div>
<?php endif; ?>
<form class="card mb-3" method="get" action="<?= url('/ocorrencias') ?>"><div class="card-body row g-2">
  <div class="col-6 col-md-3"><select name="status" class="form-select form-select-sm"><option value="">Abertas e em andamento</option><?php foreach (Incident::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-3"><select name="tipo" class="form-select form-select-sm"><option value="">Todos os tipos</option><?php foreach (Incident::KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['kind']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-12 col-md-4 d-flex gap-2 align-items-center">
    <div class="form-check form-check-inline small"><input class="form-check-input" type="checkbox" name="todas" value="1" id="todas"<?= checked($filters['all']) ?>><label class="form-check-label" for="todas">Incluir resolvidas</label></div>
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
  </div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 table-responsive-stack">
  <thead class="table-light"><tr><th>Ocorrência</th><th>Tipo</th><th>Evento / equipamento</th><th>Gravidade</th><th>Situação</th></tr></thead>
  <tbody>
  <?php if (!$result['itens']): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhuma ocorrência.</td></tr><?php endif; ?>
  <?php foreach ($result['itens'] as $i): ?>
    <tr>
      <td data-label="Ocorrência"><a href="<?= url('/ocorrencias/' . (int) $i['id']) ?>" class="fw-semibold"><?= e($i['title']) ?></a><br><span class="small text-muted">#<?= (int) $i['id'] ?> · <?= e($i['reporter_name'] ?? '—') ?> · <?= e(format_datetime($i['created_at'])) ?></span></td>
      <td data-label="Tipo"><?= e(Incident::KINDS[$i['kind']]) ?></td>
      <td data-label="Evento"><?= $i['event_title'] ? '<a href="' . url('/eventos/' . (int) $i['event_id']) . '">' . e($i['event_title']) . '</a>' : '' ?><?= $i['equipment_code'] ? ($i['event_title'] ? '<br>' : '') . '<a href="' . url('/patrimonio/' . (int) $i['equipment_id']) . '" class="font-monospace small">' . e($i['equipment_code']) . '</a> ' . e($i['equipment_name']) : '' ?><?= !$i['event_title'] && !$i['equipment_code'] ? '—' : '' ?></td>
      <td data-label="Gravidade"><span class="badge text-bg-<?= Incident::SEVERITY_COLORS[$i['severity']] ?>"><?= e(Incident::SEVERITIES[$i['severity']]) ?></span></td>
      <td data-label="Situação"><span class="badge <?= $i['status'] === 'resolvida' ? 'text-bg-success' : ($i['status'] === 'em_andamento' ? 'text-bg-primary' : 'text-bg-light border') ?>"><?= e(Incident::STATUSES[$i['status']]) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php partial('pagination', ['result' => $result]) ?>
