<?php
$months = []; foreach ($uploads as $u) { $months[] = ['label' => substr($u['ym'], 5, 2) . '/' . substr($u['ym'], 2, 2), 'qty' => (int) $u['qty'], 'mb' => round($u['bytes'] / 1048576)]; }
$chartData = [
    'frequencia' => ['labels' => array_map(static fn($r) => explode(' ', $r['name'])[0], array_slice($byUser, 0, 12)), 'confirmados' => array_map(static fn($r) => (int) $r['confirmados'], array_slice($byUser, 0, 12)), 'pendentes' => array_map(static fn($r) => (int) $r['pendentes'], array_slice($byUser, 0, 12)), 'recusados' => array_map(static fn($r) => (int) $r['recusados'], array_slice($byUser, 0, 12))],
    'ministerios' => ['labels' => array_column($ministries, 'name'), 'values' => array_map('intval', array_column($ministries, 'qty'))],
    'uploads' => ['labels' => array_column($months, 'label'), 'qty' => array_column($months, 'qty'), 'mb' => array_column($months, 'mb')],
    'audiencia' => ['labels' => array_map(static fn($a) => format_date($a['starts_at'], 'd/m'), $audience), 'pico' => array_map(static fn($a) => $a['live_peak'] === null ? null : (int) $a['live_peak'], $audience), 'presencial' => array_map(static fn($a) => $a['attendance_estimate'] === null ? null : (int) $a['attendance_estimate'], $audience)],
    'ocorrencias' => ['labels' => array_map(static fn($r) => Incident::KINDS[$r['kind']] ?? $r['kind'], $incidents), 'values' => array_map('intval', array_column($incidents, 'n'))],
];
$totalAssign = array_sum(array_map(static fn($r) => (int) $r['total'], $byUser));
$totalDecl = array_sum(array_map(static fn($r) => (int) $r['recusados'], $byUser));
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Painel do líder</h1><div class="text-muted small">Visão consolidada da mídia · últimos <?= $days ?> dias (escala inclui os próximos 30)</div></div>
  <form method="get" class="d-flex gap-1 align-items-center small"><label for="periodo" class="text-muted">Período</label><select name="periodo" id="periodo" class="form-select form-select-sm w-auto" data-autosubmit><?php foreach ([30, 90, 180, 365] as $d): ?><option value="<?= $d ?>"<?= selected($d, $days) ?>><?= $d ?> dias</option><?php endforeach; ?></select></form>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Escalas no período</div><div class="stat-value"><?= $totalAssign ?></div><div class="small text-muted"><?= $totalAssign ? round($totalDecl * 100 / $totalAssign) : 0 ?>% recusas</div></div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><a class="card stat-card h-100 text-decoration-none<?= $art['atrasados'] ? ' border border-danger' : '' ?>" href="<?= url('/artes/atrasos') ?>"><div class="card-body"><div class="text-muted small">Artes atrasadas</div><div class="stat-value <?= $art['atrasados'] ? 'text-danger' : '' ?>"><?= $art['atrasados'] ?></div><div class="small text-muted"><?= $art['sem_designer'] ?> sem designer</div></div></a></div>
  <div class="col-6 col-md-3 col-xl-2"><a class="card stat-card h-100 text-decoration-none<?= $quarantine ? ' border border-warning' : '' ?>" href="<?= url('/moderacao') ?>"><div class="card-body"><div class="text-muted small">Quarentena</div><div class="stat-value <?= $quarantine ? 'text-warning' : '' ?>"><?= $quarantine ?></div><div class="small text-muted"><?= number_format((int) $storage['qty'], 0, ',', '.') ?> arquivos · <?= e(format_bytes((int) $storage['bytes'])) ?></div></div></a></div>
  <div class="col-6 col-md-3 col-xl-2"><a class="card stat-card h-100 text-decoration-none<?= $openIncidents ? ' border border-warning' : '' ?>" href="<?= url('/ocorrencias') ?>"><div class="card-body"><div class="text-muted small">Ocorrências abertas</div><div class="stat-value <?= $openIncidents ? 'text-warning' : '' ?>"><?= $openIncidents ?></div></div></a></div>
  <div class="col-6 col-md-3 col-xl-2"><a class="card stat-card h-100 text-decoration-none<?= $overdue ? ' border border-danger' : '' ?>" href="<?= url('/patrimonio') ?>"><div class="card-body"><div class="text-muted small">Patrimônio</div><div class="stat-value"><?= (int) $equipment['total'] ?></div><div class="small text-muted"><?= (int) ($equipment['por_status']['emprestado'] ?? 0) ?> emprestados · <?= (int) ($equipment['por_status']['manutencao'] ?? 0) ?> em manutenção<?= $overdue ? ' · <span class="text-danger">' . count($overdue) . ' atrasados</span>' : '' ?></div></div></a></div>
  <div class="col-6 col-md-3 col-xl-2"><a class="card stat-card h-100 text-decoration-none<?= $ready ? ' border border-success' : '' ?>" href="<?= url('/capacitacao/equipe') ?>"><div class="card-body"><div class="text-muted small">Prontos p/ apto</div><div class="stat-value <?= $ready ? 'text-success' : '' ?>"><?= count($ready) ?></div></div></a></div>
</div>

<?php if ($openSlots): $missing = array_filter($openSlots, static fn($o) => (int) $o['slots_total'] > (int) $o['assigned_total'] || (int) $o['declined_total'] > 0); if ($missing): ?>
  <div class="alert alert-warning py-2"><i class="bi bi-people"></i> <strong><?= count($missing) ?></strong> evento(s) nos próximos 21 dias com vagas abertas ou recusas. <a href="<?= url('/escala/painel') ?>" class="alert-link">Painel da escala</a></div>
<?php endif; endif; ?>
<?php if ($pendingReports): ?>
  <div class="alert alert-info py-2"><i class="bi bi-journal-text"></i> Cultos sem relatório pós-culto: <?php foreach (array_slice($pendingReports, 0, 5) as $ev): ?><a href="<?= url('/eventos/' . (int) $ev['id'] . '/relatorio') ?>" class="alert-link"><?= e($ev['title']) ?> (<?= e(format_date($ev['starts_at'], 'd/m')) ?>)</a>; <?php endforeach; ?><?= count($pendingReports) > 5 ? '…' : '' ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Frequência na escala (12 mais escalados)</div><div class="card-body">
      <?php if (!$byUser): ?><div class="text-muted small">Sem escalas no período.</div><?php else: ?><div class="chart-box"><canvas data-chart="frequencia"></canvas></div><?php endif; ?>
      <?php if ($overloaded || $declines): ?><div class="small mt-2">
        <?php if ($overloaded): ?><div><i class="bi bi-exclamation-triangle text-warning"></i> Sobrecarga (≥ <?= SCHEDULE_OVERLOAD_PER_MONTH ?>/mês): <?= e(implode(', ', array_map(static fn($o) => $o['name'] . ' (' . $o['month'] . ')', $overloaded))) ?></div><?php endif; ?>
        <?php if ($declines): ?><div><i class="bi bi-x-circle text-danger"></i> Recusas frequentes: <?= e(implode(', ', array_map(static fn($o) => $o['name'] . ' (' . $o['recusados'] . ')', $declines))) ?></div><?php endif; ?>
      </div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Audiência das transmissões (últimos cultos com relatório)</div><div class="card-body">
      <?php if (!$audience): ?><div class="text-muted small">Nenhum relatório pós-culto com números ainda.</div><?php else: ?><div class="chart-box"><canvas data-chart="audiencia"></canvas></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Ministérios que mais pedem artes</div><div class="card-body">
      <?php if (!$ministries): ?><div class="text-muted small">Sem pedidos no período.</div><?php else: ?><div class="chart-box"><canvas data-chart="ministerios"></canvas></div><?php endif; ?>
    </div>
      <?php if ($artDelays): ?><div class="card-footer bg-white small"><i class="bi bi-alarm text-danger"></i> <?= count($artDelays) ?> pedido(s) atrasado(s) ou vencendo em 3 dias · <a href="<?= url('/artes/atrasos') ?>">ver</a></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Uso do repositório (6 meses)</div><div class="card-body">
      <?php if (!$uploads): ?><div class="text-muted small">Sem envios.</div><?php else: ?><div class="chart-box"><canvas data-chart="uploads"></canvas></div><?php endif; ?>
    </div>
      <div class="card-footer bg-white small"><?php foreach ($byCategory as $c): ?><span class="badge text-bg-light border"><?= e(FileTypes::CATEGORIES[$c['category']] ?? $c['category']) ?>: <?= e(format_bytes((int) $c['bytes'])) ?></span> <?php endforeach; ?><a href="<?= url('/armazenamento') ?>" class="ms-1">detalhes</a></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Ocorrências por tipo</div><div class="card-body">
      <?php if (!$incidents): ?><div class="text-muted small">Nenhuma ocorrência no período.</div><?php else: ?><div class="chart-box"><canvas data-chart="ocorrencias"></canvas></div><?php endif; ?>
    </div></div>
  </div>
</div>
<script type="application/json" id="chartData"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/leader.js') ?>"></script>
