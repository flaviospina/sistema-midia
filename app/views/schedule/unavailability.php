<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Minhas indisponibilidades</h1>
  <a href="<?= url('/minha-escala') ?>" class="btn btn-sm btn-outline-secondary">Minha escala</a>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <form method="post" action="<?= url('/indisponibilidades') ?>" class="card" novalidate>
      <?= Csrf::field() ?>
      <div class="card-header bg-white fw-semibold">Nova indisponibilidade</div>
      <div class="card-body">
        <div class="btn-group w-100 mb-3" role="group">
          <input type="radio" class="btn-check" name="kind" value="data" id="kindData"<?= checked(old('kind', 'data') === 'data') ?>><label class="btn btn-outline-primary btn-sm" for="kindData">Data ou período</label>
          <input type="radio" class="btn-check" name="kind" value="recorrente" id="kindRec"<?= checked(old('kind') === 'recorrente') ?>><label class="btn btn-outline-primary btn-sm" for="kindRec">Toda semana</label>
        </div>
        <div data-kind="data">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small" for="date_from">De</label><input type="date" class="form-control form-control-sm<?= invalid('date_from') ?>" id="date_from" name="date_from" value="<?= e(old('date_from')) ?>"><?= field_error('date_from') ?></div>
            <div class="col-6"><label class="form-label small" for="date_to">Até (opcional)</label><input type="date" class="form-control form-control-sm<?= invalid('date_to') ?>" id="date_to" name="date_to" value="<?= e(old('date_to')) ?>"><?= field_error('date_to') ?></div>
          </div>
        </div>
        <div data-kind="recorrente" class="d-none">
          <label class="form-label small" for="weekday">Dia da semana</label>
          <select class="form-select form-select-sm mb-2<?= invalid('weekday') ?>" id="weekday" name="weekday"><?php foreach (Event::WEEKDAYS as $i => $w): ?><option value="<?= $i ?>"<?= selected($i, old('weekday')) ?>><?= e($w) ?></option><?php endforeach; ?></select><?= field_error('weekday') ?>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small" for="time_from">Horário de (opcional)</label><input type="time" class="form-control form-control-sm<?= invalid('time_from') ?>" id="time_from" name="time_from" value="<?= e(old('time_from')) ?>"><?= field_error('time_from') ?></div>
          <div class="col-6"><label class="form-label small" for="time_to">até</label><input type="time" class="form-control form-control-sm<?= invalid('time_to') ?>" id="time_to" name="time_to" value="<?= e(old('time_to')) ?>"><?= field_error('time_to') ?></div>
        </div>
        <div class="form-text mb-2">Sem horário = o dia inteiro.</div>
        <label class="form-label small" for="reason">Motivo (opcional)</label>
        <input type="text" class="form-control form-control-sm" id="reason" name="reason" value="<?= e(old('reason')) ?>" maxlength="200" placeholder="Viagem, trabalho, faculdade…">
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Registrar</button></div>
    </form>
  </div>
  <div class="col-lg-7">
    <div class="card"><div class="card-header bg-white fw-semibold">Registradas</div>
      <?php if (!$items): ?><div class="card-body text-muted small">Nenhuma. Quando não puder servir, registre aqui: a sugestão automática respeita.</div><?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $u): $past = $u['kind'] === 'data' && ($u['date_to'] ?: $u['date_from']) < date('Y-m-d'); ?>
          <li class="list-group-item d-flex justify-content-between align-items-center<?= $past ? ' text-muted' : '' ?>">
            <span><i class="bi <?= $u['kind'] === 'recorrente' ? 'bi-arrow-repeat' : 'bi-calendar-x' ?>"></i> <?= e(Unavailability::describe($u)) ?><?= $u['reason'] ? ' <span class="text-muted small">— ' . e($u['reason']) . '</span>' : '' ?></span>
            <form method="post" action="<?= url('/indisponibilidades/' . (int) $u['id'] . '/excluir') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-x-lg"></i></button></form>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
