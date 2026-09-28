<?php $isEdit = $f !== null; $val = static fn(string $k, $d = '') => old($k, $f[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= url('/funcoes') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/funcoes/' . (int) $f['id']) : url('/funcoes') ?>" class="card narrow-card" novalidate>
  <?= Csrf::field() ?>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label required" for="name">Nome</label>
      <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="80" required>
      <?= field_error('name') ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="description">Descrição</label>
      <input type="text" class="form-control<?= invalid('description') ?>" id="description" name="description" value="<?= e($val('description')) ?>" maxlength="300">
      <?= field_error('description') ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="sort_order">Ordem de exibição</label>
      <input type="number" class="form-control<?= invalid('sort_order') ?>" id="sort_order" name="sort_order" value="<?= e($val('sort_order', '0')) ?>" min="-9999" max="9999">
      <?= field_error('sort_order') ?>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="active" value="1" id="active"<?= checked((string) $val('active', '1') === '1') ?>>
      <label class="form-check-label" for="active">Ativa</label>
    </div>
  </div>
  <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button></div>
</form>
