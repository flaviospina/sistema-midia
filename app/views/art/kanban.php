<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Kanban de artes</h1>
  <div class="d-flex gap-2"><a href="<?= url('/artes') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> Lista</a><a href="<?= url('/artes/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo pedido</a></div>
</div>
<div class="kanban">
  <?php foreach ($columns as $status => $items): ?>
    <div class="kanban-col">
      <div class="kanban-head"><span class="badge text-bg-<?= ArtRequest::STATUS_COLORS[$status] ?>"><?= e(ArtRequest::STATUSES[$status]) ?></span> <span class="text-muted small"><?= count($items) ?></span></div>
      <?php foreach ($items as $r): $late = $r['publish_on'] < date('Y-m-d'); ?>
        <a href="<?= url('/artes/' . (int) $r['id']) ?>" class="kanban-card<?= $late ? ' late' : '' ?>">
          <div class="fw-semibold small"><?= e($r['title']) ?></div>
          <div class="small text-muted"><?= e($r['ministry_name'] ?? $r['requester_name']) ?></div>
          <div class="small d-flex justify-content-between mt-1">
            <span class="<?= $late ? 'text-danger fw-bold' : 'text-muted' ?>"><i class="bi bi-calendar-check"></i> <?= e(format_date($r['publish_on'], 'd/m')) ?></span>
            <span><?php foreach (ArtRequest::formatsOf($r) as $f): ?><span class="badge text-bg-light border"><?= e(ucfirst($f)) ?></span> <?php endforeach; ?></span>
          </div>
          <?php if ($r['designer_name'] || $r['is_urgent']): ?><div class="small mt-1"><?= $r['designer_name'] ? '<i class="bi bi-brush"></i> ' . e($r['designer_name']) : '' ?><?= $r['is_urgent'] ? ' <span class="badge text-bg-danger">urgente</span>' : '' ?><?= $r['versions_count'] ? ' <span class="text-muted">v' . (int) $r['current_version'] . '</span>' : '' ?></div><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$items): ?><div class="small text-muted text-center py-2">—</div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
