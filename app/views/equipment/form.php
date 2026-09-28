<?php $isEdit = $q !== null; $val = static fn(string $k, $d = '') => old($k, $q[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/patrimonio/' . (int) $q['id']) : url('/patrimonio') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/patrimonio/' . (int) $q['id']) : url('/patrimonio') ?>" class="row g-3" enctype="multipart/form-data" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-8"><div class="card"><div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label required" for="code">Código (etiqueta)</label><input type="text" class="form-control font-monospace<?= invalid('code') ?>" id="code" name="code" value="<?= e(old('code', $q['code'] ?? $nextCode)) ?>" maxlength="20" required><?= field_error('code') ?></div>
    <div class="col-md-8"><label class="form-label required" for="name">Nome</label><input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" required placeholder="Ex.: Microfone sem fio Shure"><?= field_error('name') ?></div>
    <div class="col-md-4"><label class="form-label" for="category">Categoria</label><select class="form-select" id="category" name="category"><?php foreach (Equipment::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('category', 'outro')) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label" for="brand">Marca</label><input type="text" class="form-control" id="brand" name="brand" value="<?= e($val('brand')) ?>" maxlength="80"></div>
    <div class="col-md-4"><label class="form-label" for="model">Modelo</label><input type="text" class="form-control" id="model" name="model" value="<?= e($val('model')) ?>" maxlength="80"></div>
    <div class="col-md-4"><label class="form-label" for="serial_number">Nº de série</label><input type="text" class="form-control" id="serial_number" name="serial_number" value="<?= e($val('serial_number')) ?>" maxlength="80"></div>
    <div class="col-md-4"><label class="form-label" for="acquired_on">Data de aquisição</label><input type="date" class="form-control<?= invalid('acquired_on') ?>" id="acquired_on" name="acquired_on" value="<?= e($val('acquired_on')) ?>"><?= field_error('acquired_on') ?></div>
    <div class="col-md-4"><label class="form-label" for="value">Valor (R$)</label><input type="text" class="form-control" id="value" name="value" inputmode="decimal" value="<?= e(old('value', isset($q['value_cents']) && $q['value_cents'] !== null ? number_format($q['value_cents'] / 100, 2, ',', '.') : '')) ?>" placeholder="0,00"></div>
    <div class="col-md-6"><label class="form-label" for="location">Local de guarda</label><input type="text" class="form-control" id="location" name="location" value="<?= e($val('location')) ?>" maxlength="120" placeholder="Ex.: Mesa de som, armário 2"></div>
    <?php if ($isEdit): ?>
      <div class="col-md-6"><label class="form-label" for="status">Situação</label>
        <select class="form-select" id="status" name="status"<?= $q['status'] === 'emprestado' ? ' disabled' : '' ?>><?php foreach (Equipment::STATUSES as $k => $l): if ($k === 'emprestado' && $q['status'] !== 'emprestado') continue; ?><option value="<?= e($k) ?>"<?= selected($k, $val('status')) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <?php if ($q['status'] === 'emprestado'): ?><div class="form-text">Item emprestado: registre a devolução na tela do item.</div><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="col-12"><label class="form-label" for="notes">Observações</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="3000" placeholder="Acessórios inclusos, garantia, cuidados especiais…"><?= e($val('notes')) ?></textarea></div>
  </div></div></div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Foto</div><div class="card-body">
      <div id="photoPreview" class="mb-2"><?php if ($isEdit && $q['photo_path']): ?><img src="<?= url('/patrimonio/' . (int) $q['id'] . '/foto') ?>" class="avatar avatar-96 rounded" alt=""><?php endif; ?></div>
      <input type="file" class="form-control form-control-sm" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-mb="<?= PHOTO_MAX_MB ?>">
      <div class="form-text">JPG, PNG ou WebP até <?= PHOTO_MAX_MB ?> MB. É redimensionada no servidor.</div>
      <?php if ($isEdit && $q['photo_path']): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label small" for="remove_photo">Remover foto atual</label></div><?php endif; ?>
    </div></div>
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
