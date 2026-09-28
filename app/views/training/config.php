<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Trilhas de capacitação</h1><div class="text-muted small">Treinamentos por função. Os obrigatórios precisam ser validados para o aprendiz virar apto.</div></div>
  <a href="<?= url('/capacitacao/trilhas/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo treinamento</a>
</div>
<div class="row g-3">
<?php foreach ($functions as $f): $fid = (int) $f['id']; $items = $byFunction[$fid]['items'] ?? []; ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span><?= e($f['name']) ?></span><a href="<?= url('/capacitacao/trilhas/novo', ['funcao' => $fid]) ?>" class="small">+ adicionar</a></div>
      <?php if (!$items): ?><div class="card-body small text-muted">Sem treinamentos.</div><?php else: ?>
      <ul class="list-group list-group-flush small">
        <?php foreach ($items as $t): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center gap-2<?= $t['active'] ? '' : ' text-muted' ?>">
            <span><span class="text-muted me-1"><?= (int) $t['sort_order'] ?></span><?= e($t['title']) ?><?= $t['required'] ? '' : ' <span class="badge text-bg-light border">opcional</span>' ?><?= $t['active'] ? '' : ' <span class="badge text-bg-secondary">inativo</span>' ?></span>
            <a href="<?= url('/capacitacao/trilhas/' . (int) $t['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
