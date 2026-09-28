<?php $eid = (int) $e['id']; $past = strtotime($e['starts_at']) < time(); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/eventos', ['mes' => substr($e['starts_at'], 0, 7)]) ?>">Eventos</a></li>
  <li class="breadcrumb-item active"><?= e($e['title']) ?></li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e($e['title']) ?>
      <?php if ($e['status'] === 'cancelado'): ?><span class="badge text-bg-secondary">Cancelado</span><?php elseif ($e['status'] === 'concluido'): ?><span class="badge text-bg-light border">Concluído</span><?php endif; ?>
    </h1>
    <div class="text-muted"><i class="bi bi-calendar-event"></i> <?= e(Event::WEEKDAYS[(int) date('w', strtotime($e['starts_at']))]) ?>, <?= e(format_datetime($e['starts_at'])) ?><?= $e['ends_at'] ? ' às ' . e(substr($e['ends_at'], 11, 5)) : '' ?>
      · <?= e(Event::TYPES[$e['event_type']]) ?><?= $e['location'] ? ' · <i class="bi bi-geo-alt"></i> ' . e($e['location']) : '' ?><?= $e['ministry_name'] ? ' · ' . e($e['ministry_name']) : '' ?>
      <?php if ($e['recurrence_title']): ?> · <span class="badge text-bg-light border" title="Gerado automaticamente">culto fixo</span><?php endif; ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (Auth::can('schedule.manage') && $e['status'] === 'agendado'): ?><a href="<?= url('/eventos/' . $eid . '/escala') ?>" class="btn btn-sm btn-primary"><i class="bi bi-people"></i> Montar escala</a><?php endif; ?>
    <?php if (Auth::can('events.manage')): ?>
      <a href="<?= url('/eventos/' . $eid . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
      <?php if ($e['status'] === 'agendado'): ?>
        <form method="post" action="<?= url('/eventos/' . $eid . '/cancelar') ?>" data-confirm="Cancelar este evento? As pessoas escaladas serão avisadas em Minha escala."><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Cancelar evento</button></form>
      <?php elseif ($e['status'] === 'cancelado' && !$past): ?>
        <form method="post" action="<?= url('/eventos/' . $eid . '/reativar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-success">Reativar</button></form>
      <?php endif; ?>
      <?php if ((int) $e['assigned_total'] === 0): ?>
        <form method="post" action="<?= url('/eventos/' . $eid . '/excluir') ?>" data-confirm="Excluir definitivamente este evento?" data-confirm-type="danger"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <?php if ($e['description']): ?><div class="card mb-3"><div class="card-body"><?= nl2br(e($e['description'])) ?></div></div><?php endif; ?>
    <?php if (isset($slots)): ?>
    <div class="card mb-3">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Escala</span><span class="small text-muted"><?= (int) $e['confirmed_total'] ?> confirmado(s) · <?= (int) $e['assigned_total'] ?>/<?= (int) $e['slots_total'] ?> vaga(s)</span></div>
      <?php if (!$slots): ?><div class="card-body text-muted small">Vagas ainda não definidas.</div><?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($slots as $s): $list = array_filter($assignments[(int) $s['function_id']] ?? [], static fn($a) => $a['status'] !== 'recusado'); ?>
          <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold minw-130"><?= e($s['function_name']) ?> <span class="text-muted small">×<?= (int) $s['quantity'] ?></span></span>
            <?php foreach ($list as $a): ?>
              <span class="badge rounded-pill <?= $a['status'] === 'confirmado' ? 'text-bg-success' : 'text-bg-warning' ?>" title="<?= e(Assignment::STATUSES[$a['status']]) ?>"><?= e($a['user_name']) ?></span>
            <?php endforeach; ?>
            <?php for ($i = count($list); $i < (int) $s['quantity']; $i++): ?><span class="badge rounded-pill text-bg-light border text-muted">vaga aberta</span><?php endfor; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($e['notes']): ?><div class="card-footer bg-white small"><i class="bi bi-info-circle"></i> <?= nl2br(e($e['notes'])) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($files): ?>
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 text-muted mb-0">Arquivos deste evento</h2><a href="<?= url('/arquivos/buscar', ['evento' => $e['title']]) ?>" class="small">ver todos</a></div>
      <div class="file-grid"><?php foreach ($files as $file) partial('file_card', ['file' => $file, 'selectable' => false]); ?></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-4">
    <?php if (!empty($mine)): ?>
      <div class="card mb-3 border-primary"><div class="card-header bg-white fw-semibold">Você está escalado(a)</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($mine as $a): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($a['function_name']) ?></span><span class="badge <?= $a['status'] === 'confirmado' ? 'text-bg-success' : ($a['status'] === 'recusado' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= e(Assignment::STATUSES[$a['status']]) ?></span></li><?php endforeach; ?>
        </ul>
        <div class="card-footer bg-white"><a href="<?= url('/minha-escala') ?>" class="btn btn-sm btn-primary w-100">Confirmar / recusar</a></div>
      </div>
    <?php endif; ?>
    <?php if (Auth::can('files.upload') && $e['status'] !== 'cancelado'): ?>
      <a href="<?= url('/arquivos/enviar', ['evento' => $eid]) ?>" class="btn btn-outline-secondary btn-sm w-100 mb-3"><i class="bi bi-cloud-arrow-up"></i> Enviar fotos/vídeos deste evento</a>
    <?php endif; ?>
    <?php if ($e['status'] !== 'cancelado' && (Auth::can('checklist.fill') || Auth::can('reports.fill') || Auth::can('incidents.report'))): $progress = Auth::can('checklist.fill') ? Checklist::progress($eid) : []; $pd = array_sum(array_column($progress, 'done')); $pt = array_sum(array_column($progress, 'total')); $hasReport = Auth::can('reports.fill') && Incident::report($eid) !== null; ?>
      <div class="card mb-3"><div class="card-header bg-white fw-semibold">Operação</div><div class="list-group list-group-flush">
        <?php if (Auth::can('checklist.fill')): ?><a href="<?= url('/eventos/' . $eid . '/checklist') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><span><i class="bi bi-check2-square"></i> Checklist pré-culto</span><?= $pt ? '<span class="badge ' . ($pd >= $pt ? 'text-bg-success' : 'text-bg-light border') . '">' . $pd . '/' . $pt . '</span>' : '' ?></a><?php endif; ?>
        <?php if (Auth::can('reports.fill') && $past): ?><a href="<?= url('/eventos/' . $eid . '/relatorio') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><span><i class="bi bi-journal-text"></i> Relatório pós-culto</span><?= $hasReport ? '<span class="badge text-bg-success">preenchido</span>' : '<span class="badge text-bg-warning">pendente</span>' ?></a><?php endif; ?>
        <?php if (Auth::can('incidents.report')): ?><a href="<?= url('/ocorrencias/nova', ['evento' => $eid]) ?>" class="list-group-item list-group-item-action"><i class="bi bi-exclamation-triangle"></i> Registrar ocorrência</a><?php endif; ?>
      </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-body small text-muted">Criado em <?= e(format_datetime($e['created_at'])) ?>.</div></div>
  </div>
</div>
