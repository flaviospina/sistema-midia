<?php $eid = (int) $e['id']; $progress = Checklist::progress($eid); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/eventos') ?>">Eventos</a></li>
  <li class="breadcrumb-item"><a href="<?= url('/eventos/' . $eid) ?>"><?= e($e['title']) ?></a></li>
  <li class="breadcrumb-item active">Checklist pré-culto</li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Checklist pré-culto</h1><div class="text-muted small"><?= e($e['title']) ?> · <?= e(format_datetime($e['starts_at'])) ?></div></div>
  <?php if ($canAll): ?><a href="<?= url('/checklist') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i> Configurar itens</a><?php endif; ?>
</div>
<?php if (!$byFunction): ?><div class="alert alert-info">Nenhum item configurado ainda.</div><?php endif; ?>
<?php if (!$myFunctions && !$canAll): ?><div class="alert alert-warning">Você não está escalado(a) neste evento; o checklist fica só para leitura.</div><?php endif; ?>
<div class="row g-3">
<?php foreach ($byFunction as $fid => $group): $can = $canAll || in_array($fid, $myFunctions, true); $p = $progress[$fid] ?? ['done' => 0, 'total' => count($group['items'])]; $names = array_map(static fn($a) => $a['user_name'], array_filter($assignments[$fid] ?? [], static fn($a) => $a['status'] !== 'recusado')); ?>
  <div class="col-md-6 col-xl-4">
    <form method="post" action="<?= url('/eventos/' . $eid . '/checklist/' . $fid) ?>" class="card h-100<?= $can ? ' border-primary' : '' ?>"><?= Csrf::field() ?>
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e($group['name']) ?></span>
        <span class="badge <?= $p['done'] >= $p['total'] && $p['total'] ? 'text-bg-success' : 'text-bg-light border' ?>"><?= $p['done'] ?>/<?= $p['total'] ?></span>
      </div>
      <?php if ($names): ?><div class="card-body py-1 small text-muted border-bottom"><i class="bi bi-person"></i> <?= e(implode(', ', $names)) ?></div><?php endif; ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($group['items'] as $it): $iid = (int) $it['id']; $c = $checks[$iid] ?? null; ?>
          <li class="list-group-item check-item">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="items[]" value="<?= $iid ?>" id="it<?= $iid ?>"<?= checked($c !== null) ?><?= $can ? '' : ' disabled' ?>>
              <label class="form-check-label" for="it<?= $iid ?>"><?= e($it['label']) ?></label>
              <?php if ($c): ?><div class="small text-muted ms-1"><?= e($c['user_name'] ?? '') ?> · <?= e(format_date($c['checked_at'], 'd/m H:i')) ?></div><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($can): ?><div class="card-footer bg-white"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-check2-square"></i> Salvar <?= e(mb_strtolower($group['name'])) ?></button></div><?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>
</div>
