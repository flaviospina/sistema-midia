<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Painel da escala</h1>
  <form method="get" class="d-flex gap-1 align-items-center small">
    <input type="date" name="de" class="form-control form-control-sm" value="<?= e($from) ?>"> a <input type="date" name="ate" class="form-control form-control-sm" value="<?= e($to) ?>">
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
  </form>
</div>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Próximos 21 dias</div>
      <?php if (!$open): ?><div class="card-body text-muted small">Nenhum evento agendado.</div><?php else: ?>
      <table class="table table-sm mb-0 small"><thead><tr><th>Evento</th><th class="text-center">Vagas</th><th class="text-center">Pend.</th><th class="text-center">Recus.</th></tr></thead><tbody>
      <?php foreach ($open as $o): $missing = (int) $o['slots_total'] - (int) $o['assigned_total']; ?>
        <tr class="<?= $missing > 0 ? 'table-warning' : '' ?>">
          <td><a href="<?= url('/eventos/' . (int) $o['id'] . '/escala') ?>"><?= e($o['title']) ?></a><br><span class="text-muted"><?= e(format_datetime($o['starts_at'])) ?></span></td>
          <td class="text-center"><?= (int) $o['assigned_total'] ?>/<?= (int) $o['slots_total'] ?><?= $missing > 0 ? ' <span class="badge text-bg-warning">' . $missing . '</span>' : '' ?></td>
          <td class="text-center"><?= (int) $o['pending_total'] ?></td>
          <td class="text-center"><?= (int) $o['declined_total'] ?: '' ?></td>
        </tr>
      <?php endforeach; ?></tbody></table>
      <?php endif; ?>
    </div>
    <?php if ($overloaded): ?>
      <div class="card mb-3 border-warning"><div class="card-header bg-white fw-semibold">Sobrecarga (≥ <?= SCHEDULE_OVERLOAD_PER_MONTH ?> escalas em 30 dias)</div>
        <ul class="list-group list-group-flush small"><?php foreach ($overloaded as $o): if (!$o['user']) continue; ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url('/usuarios/' . (int) $o['user']['id']) ?>"><?= e($o['user']['name']) ?></a><span><?= (int) $o['month'] ?> escalas</span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($swaps): ?>
      <div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Trocas aguardando aprovação</span><a href="<?= url('/trocas') ?>" class="small">ver</a></div>
        <ul class="list-group list-group-flush small"><?php foreach ($swaps as $s): ?><li class="list-group-item"><?= e($s['from_name']) ?> → <?= e($s['to_name']) ?> · <?= e($s['function_name']) ?> · <?= e($s['event_title']) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-6">
    <div class="card"><div class="card-header bg-white fw-semibold">Frequência por pessoa (<?= e(format_date($from)) ?> a <?= e(format_date($to)) ?>)</div>
      <table class="table table-sm mb-0 small"><thead><tr><th>Pessoa</th><th class="text-center">Confirm.</th><th class="text-center">Pend.</th><th class="text-center">Recusas</th><th class="text-center">Total</th></tr></thead><tbody>
      <?php if (!$byUser): ?><tr><td colspan="5" class="text-muted">Sem escalas no período.</td></tr><?php endif; ?>
      <?php foreach ($byUser as $u): ?>
        <tr><td><a href="<?= url('/usuarios/' . (int) $u['id']) ?>"><?= e($u['name']) ?></a></td><td class="text-center"><?= (int) $u['confirmados'] ?></td><td class="text-center"><?= (int) $u['pendentes'] ?></td><td class="text-center <?= (int) $u['recusados'] >= 2 ? 'text-danger fw-bold' : '' ?>"><?= (int) $u['recusados'] ?></td><td class="text-center"><?= (int) $u['total'] ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </div>
</div>
