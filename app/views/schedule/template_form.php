<?php $isEdit = $t !== null; $val = static fn(string $k, $d = '') => old($k, $t[$k] ?? $d); $oldQty = old('qty', null); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= url('/modelos') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/modelos/' . (int) $t['id']) : url('/modelos') ?>" class="card narrow-card" novalidate>
  <?= Csrf::field() ?>
  <div class="card-body">
    <div class="mb-3"><label class="form-label required" for="name">Nome</label><input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="100" required><?= field_error('name') ?></div>
    <div class="mb-3"><label class="form-label" for="description">Descrição</label><input type="text" class="form-control" id="description" name="description" value="<?= e($val('description')) ?>" maxlength="300"></div>
    <div class="fw-semibold small mb-1">Vagas por função</div><?= field_error('qty') ?>
    <?php foreach ($functions as $f): $fid = (int) $f['id']; $q = is_array($oldQty) ? ($oldQty[$fid] ?? 0) : ($slots[$fid] ?? 0); ?>
      <div class="d-flex align-items-center justify-content-between mb-1"><label class="small" for="qty<?= $fid ?>"><?= e($f['name']) ?></label><input type="number" class="form-control form-control-sm w-70" id="qty<?= $fid ?>" name="qty[<?= $fid ?>]" value="<?= (int) $q ?>" min="0" max="20"></div>
    <?php endforeach; ?>
    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="active" value="1" id="active"<?= checked((string) $val('active', '1') === '1') ?>><label class="form-check-label" for="active">Ativo</label></div>
  </div>
  <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button></div>
</form>
