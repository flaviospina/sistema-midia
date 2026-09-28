<?php $id = (int) $i['id']; $canManage = Auth::can('incidents.manage'); $mine = (int) $i['reported_by'] === Auth::id(); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/ocorrencias') ?>">Ocorrências</a></li>
  <li class="breadcrumb-item active">#<?= $id ?></li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e($i['title']) ?> <span class="badge text-bg-<?= Incident::SEVERITY_COLORS[$i['severity']] ?>"><?= e(Incident::SEVERITIES[$i['severity']]) ?></span> <span class="badge <?= $i['status'] === 'resolvida' ? 'text-bg-success' : ($i['status'] === 'em_andamento' ? 'text-bg-primary' : 'text-bg-light border') ?>"><?= e(Incident::STATUSES[$i['status']]) ?></span></h1>
    <div class="text-muted small"><?= e(Incident::KINDS[$i['kind']]) ?> · registrada por <?= e($i['reporter_name'] ?? '—') ?> em <?= e(format_datetime($i['created_at'])) ?></div>
  </div>
  <?php if ($mine || $canManage): ?><a href="<?= url('/ocorrencias/' . $id . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body"><?= $i['description'] ? nl2br(e($i['description'])) : '<span class="text-muted">Sem detalhes.</span>' ?></div>
      <?php if ($i['event_title'] || $i['equipment_code']): ?><div class="card-footer bg-white small">
        <?php if ($i['event_title']): ?><i class="bi bi-calendar-event"></i> <a href="<?= url('/eventos/' . (int) $i['event_id']) ?>"><?= e($i['event_title']) ?></a> (<?= e(format_datetime($i['starts_at'])) ?>) <?php endif; ?>
        <?php if ($i['equipment_code']): ?>· <i class="bi bi-box-seam"></i> <a href="<?= url('/patrimonio/' . (int) $i['equipment_id']) ?>"><?= e($i['equipment_code']) ?> <?= e($i['equipment_name']) ?></a><?php endif; ?>
      </div><?php endif; ?>
    </div>
    <?php if ($i['resolution']): ?>
      <div class="card mb-3 border-success"><div class="card-header bg-white fw-semibold"><i class="bi bi-check2-circle"></i> Resolução<?= $i['resolved_at'] ? ' · ' . e($i['resolver_name'] ?? '') . ' em ' . e(format_datetime($i['resolved_at'])) : '' ?></div><div class="card-body"><?= nl2br(e($i['resolution'])) ?></div></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-4">
    <?php if ($canManage): ?>
    <form method="post" action="<?= url('/ocorrencias/' . $id . '/status') ?>" class="card"><?= Csrf::field() ?>
      <div class="card-header bg-white fw-semibold">Atualizar situação</div>
      <div class="card-body">
        <select class="form-select form-select-sm mb-2" name="status" aria-label="Situação"><?php foreach (Incident::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $i['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <textarea class="form-control form-control-sm" name="resolution" rows="4" maxlength="3000" placeholder="Como foi resolvida / o que foi feito (obrigatório ao resolver)"><?= e($i['resolution'] ?? '') ?></textarea>
      </div>
      <div class="card-footer bg-white"><button class="btn btn-sm btn-primary w-100">Salvar</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>
