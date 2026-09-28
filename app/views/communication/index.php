<?php $q = static fn(array $x): string => http_build_query(array_filter(['mes' => $month, 'canal' => $channel] + $x)); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <a href="?<?= $q(['mes' => $prev]) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></a>
    <h1 class="h4 mb-0">Comunicação · <?= e($label) ?></h1>
    <a href="?<?= $q(['mes' => $next]) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Próximo mês"><i class="bi bi-chevron-right"></i></a>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <form method="get" class="d-flex gap-1"><input type="hidden" name="mes" value="<?= e($month) ?>">
      <select name="canal" class="form-select form-select-sm" data-autosubmit aria-label="Canal"><option value="">Todos os canais</option><?php foreach (Publication::CHANNELS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $channel) ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
    <?php if (Auth::can('publications.manage')): ?><a href="<?= url('/comunicacao/nova') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Publicação</a><?php endif; ?>
  </div>
</div>
<?php if (!$items): ?><div class="card"><div class="card-body text-center text-muted py-4">Nenhuma publicação agendada neste mês. Artes aprovadas entram aqui automaticamente.</div></div><?php endif; ?>
<?php foreach ($byDay as $day => $list): $past = $day < date('Y-m-d'); ?>
  <div class="small fw-semibold mt-3 mb-1 <?= $day === date('Y-m-d') ? 'text-primary' : 'text-muted' ?>"><?= e(Event::WEEKDAYS[(int) date('w', strtotime($day))]) ?>, <?= e(format_date($day)) ?><?= $day === date('Y-m-d') ? ' · hoje' : '' ?></div>
  <?php foreach ($list as $p): $pid = (int) $p['id']; ?>
    <div class="card mb-1<?= $p['status'] === 'cancelado' ? ' opacity-50' : ($p['status'] === 'planejado' && $past ? ' border-danger' : '') ?>">
      <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
        <span class="badge text-bg-<?= $p['status'] === 'publicado' ? 'success' : ($p['status'] === 'cancelado' ? 'secondary' : 'light border text-dark') ?>"><i class="bi <?= Publication::CHANNEL_ICONS[$p['channel']] ?>"></i> <?= e(Publication::CHANNELS[$p['channel']]) ?></span>
        <span class="text-muted small"><?= e(substr($p['publish_at'], 11, 5)) ?></span>
        <?php if ($p['thumb_ref']): ?><a href="<?= url('/arquivos/' . (int) $p['file_id']) ?>"><img src="<?= url('/arquivos/' . (int) $p['file_id'] . '/miniatura') ?>" class="thumb-sm" alt=""></a><?php endif; ?>
        <span class="flex-grow-1"><span class="fw-semibold"><?= e($p['title']) ?></span>
          <span class="small text-muted d-block"><?= $p['request_id'] ? '<a href="' . url('/artes/' . (int) $p['request_id']) . '">pedido #' . (int) $p['request_id'] . '</a> · ' : '' ?><?= $p['event_title'] ? e($p['event_title']) . ' · ' : '' ?><?= $p['responsible_name'] ? e($p['responsible_name']) : '' ?><?= $p['notes'] ? ' · ' . e($p['notes']) : '' ?><?= $p['link'] ? ' · <a href="' . e($p['link']) . '" target="_blank" rel="noopener">ver post</a>' : '' ?></span></span>
        <?php if (Auth::can('publications.manage')): ?>
          <div class="d-flex gap-1">
            <?php if ($p['status'] === 'planejado'): ?>
              <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#pub<?= $pid ?>" title="Marcar como publicado"><i class="bi bi-check-lg"></i></button>
            <?php endif; ?>
            <a href="<?= url('/comunicacao/' . $pid . '/editar') ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
            <?php if ($p['status'] !== 'publicado'): ?><form method="post" action="<?= url('/comunicacao/' . $pid . '/cancelar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary" title="<?= $p['status'] === 'cancelado' ? 'Reativar' : 'Cancelar' ?>"><i class="bi <?= $p['status'] === 'cancelado' ? 'bi-arrow-counterclockwise' : 'bi-x-lg' ?>"></i></button></form><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($p['status'] === 'planejado' && Auth::can('publications.manage')): ?>
        <form method="post" action="<?= url('/comunicacao/' . $pid . '/publicar') ?>" class="collapse" id="pub<?= $pid ?>"><?= Csrf::field() ?>
          <div class="card-footer bg-white d-flex gap-1"><input type="url" name="link" class="form-control form-control-sm" placeholder="Link do post (opcional)"><button class="btn btn-sm btn-success">Publicado</button></div></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
