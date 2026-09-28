<?php $typeColor = ['culto' => 'primary', 'especial' => 'danger', 'ensaio' => 'info', 'reuniao' => 'secondary', 'outro' => 'dark']; $q = static fn(array $x): string => http_build_query(array_filter(['mes' => $month, 'tipo' => $type, 'visao' => $view] + $x)); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <a href="?<?= $q(['mes' => $prev]) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></a>
    <h1 class="h4 mb-0"><?= e($label) ?></h1>
    <a href="?<?= $q(['mes' => $next]) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Próximo mês"><i class="bi bi-chevron-right"></i></a>
    <a href="?<?= $q(['mes' => date('Y-m')]) ?>" class="btn btn-sm btn-link">hoje</a>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <form method="get" class="d-flex gap-1">
      <input type="hidden" name="mes" value="<?= e($month) ?>"><input type="hidden" name="visao" value="<?= e($view) ?>">
      <select name="tipo" class="form-select form-select-sm" data-autosubmit aria-label="Tipo">
        <option value="">Todos os tipos</option>
        <?php foreach (Event::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $type) ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm btn-outline-secondary">Filtrar</button></noscript>
    </form>
    <div class="btn-group btn-group-sm">
      <a href="?<?= $q(['visao' => 'calendario']) ?>" class="btn btn-outline-secondary<?= $view === 'calendario' ? ' active' : '' ?>"><i class="bi bi-calendar3"></i></a>
      <a href="?<?= $q(['visao' => 'lista']) ?>" class="btn btn-outline-secondary<?= $view === 'lista' ? ' active' : '' ?>"><i class="bi bi-list-ul"></i></a>
    </div>
    <?php if (Auth::can('schedule.self')): ?><a href="<?= url('/minha-escala') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-check"></i> Minha escala</a><?php endif; ?>
    <?php if (Auth::can('events.manage')): ?>
      <a href="<?= url('/eventos/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Evento</a>
      <div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-gear"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= url('/recorrencias') ?>">Cultos fixos</a></li>
          <li><a class="dropdown-item" href="<?= url('/modelos') ?>">Modelos de escala</a></li>
          <li><a class="dropdown-item" href="<?= url('/escala/painel') ?>">Painel da escala</a></li>
          <li><a class="dropdown-item" href="<?= url('/trocas') ?>">Pedidos de troca</a></li>
          <li><a class="dropdown-item" href="<?= url('/indisponibilidades/equipe') ?>">Indisponibilidades da equipe</a></li>
        </ul></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($view === 'calendario'): ?>
<div class="card d-none d-md-block">
  <table class="table table-bordered calendar mb-0">
    <thead class="table-light"><tr><?php foreach (Event::WEEKDAYS as $w): ?><th class="text-center small"><?= e($w) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($weeks as $week): ?>
      <tr>
      <?php foreach ($week as $day): ?>
        <td class="<?= $day['inMonth'] ? '' : 'text-muted bg-light' ?><?= $day['today'] ? ' cal-today' : '' ?>">
          <div class="d-flex justify-content-between"><span class="cal-day"><?= $day['day'] ?></span>
            <?php if (Auth::can('events.manage') && $day['inMonth']): ?><a href="<?= url('/eventos/novo', ['data' => $day['date']]) ?>" class="cal-add" title="Novo evento"><i class="bi bi-plus"></i></a><?php endif; ?></div>
          <?php foreach ($day['events'] as $ev): ?>
            <a href="<?= url('/eventos/' . (int) $ev['id']) ?>" class="cal-event bg-<?= $typeColor[$ev['event_type']] ?? 'secondary' ?><?= $ev['status'] === 'cancelado' ? ' cancelled' : '' ?>" title="<?= e($ev['title']) ?>">
              <?= e(substr($ev['starts_at'], 11, 5)) ?> <?= e($ev['title']) ?>
              <?php if (Auth::can('schedule.view') && (int) $ev['slots_total'] > 0): ?><span class="cal-fill"><?= (int) $ev['assigned_total'] ?>/<?= (int) $ev['slots_total'] ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </td>
      <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php // Lista (sempre no celular; opcional no desktop) ?>
<div class="<?= $view === 'calendario' ? 'd-md-none' : '' ?>">
  <?php if (!$events): ?><div class="card"><div class="card-body text-center text-muted py-4">Nenhum evento neste mês.</div></div><?php endif; ?>
  <?php $lastDay = ''; foreach ($events as $ev): $d = substr($ev['starts_at'], 0, 10); ?>
    <?php if ($d !== $lastDay): $lastDay = $d; ?><div class="small text-muted fw-semibold mt-3 mb-1"><?= e(Event::WEEKDAYS[(int) date('w', strtotime($d))]) ?>, <?= e(format_date($d)) ?></div><?php endif; ?>
    <a href="<?= url('/eventos/' . (int) $ev['id']) ?>" class="card mb-1 text-decoration-none text-body<?= $ev['status'] === 'cancelado' ? ' opacity-50' : '' ?>">
      <div class="card-body py-2 d-flex align-items-center gap-3">
        <span class="badge text-bg-<?= $typeColor[$ev['event_type']] ?? 'secondary' ?>"><?= e(substr($ev['starts_at'], 11, 5)) ?></span>
        <span class="flex-grow-1"><span class="fw-semibold"><?= e($ev['title']) ?></span><?= $ev['status'] === 'cancelado' ? ' <span class="badge text-bg-secondary">cancelado</span>' : '' ?><?php if ($ev['location']): ?><span class="small text-muted d-block"><?= e($ev['location']) ?></span><?php endif; ?></span>
        <?php if (Auth::can('schedule.view') && (int) $ev['slots_total'] > 0): ?><span class="small text-muted text-nowrap"><i class="bi bi-people"></i> <?= (int) $ev['assigned_total'] ?>/<?= (int) $ev['slots_total'] ?></span><?php endif; ?>
      </div>
    </a>
  <?php endforeach; ?>
</div>
