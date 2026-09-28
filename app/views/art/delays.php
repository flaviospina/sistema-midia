<h1 class="h4 mb-3">Atrasos e prazos</h1>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Atrasados</div><div class="stat-value <?= $counts['atrasados'] ? 'text-danger' : '' ?>"><?= $counts['atrasados'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Sem designer</div><div class="stat-value"><?= $counts['sem_designer'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Aguardando mídia</div><div class="stat-value"><?= $counts['aprov_midia'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="text-muted small">Aguardando pastor</div><div class="stat-value"><?= $counts['aprov_pastoral'] ?></div></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-header bg-white fw-semibold">Publicação em até 3 dias (ou já passada) sem aprovação</div>
    <?php if (!$items): ?><div class="card-body text-muted small">Nada em risco.</div><?php else: ?>
    <table class="table table-sm mb-0 small"><thead><tr><th>Pedido</th><th>Publicação</th><th>Situação</th><th>Designer</th></tr></thead><tbody>
    <?php foreach ($items as $r): $late = $r['publish_on'] < date('Y-m-d'); ?>
      <tr class="<?= $late ? 'table-danger' : 'table-warning' ?>"><td><a href="<?= url('/artes/' . (int) $r['id']) ?>"><?= e($r['title']) ?></a><br><span class="text-muted"><?= e($r['ministry_name'] ?? $r['requester_name']) ?></span></td><td><?= e(format_date($r['publish_on'])) ?></td><td><span class="badge text-bg-<?= ArtRequest::STATUS_COLORS[$r['status']] ?>"><?= e(ArtRequest::STATUSES[$r['status']]) ?></span></td><td><?= e($r['designer_name'] ?? '—') ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header bg-white fw-semibold">Ministérios que mais pedem (180 dias)</div>
    <table class="table table-sm mb-0 small"><tbody><?php if (!$ministries): ?><tr><td class="text-muted">Sem pedidos.</td></tr><?php endif; ?><?php foreach ($ministries as $m): ?><tr><td><?= e($m['name']) ?></td><td class="text-end"><?= (int) $m['qty'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
