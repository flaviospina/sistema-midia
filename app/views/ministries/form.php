<?php $isEdit = $m !== null; $val = static fn(string $k, $d = '') => old($k, $m[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= url('/ministerios') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" action="<?= $isEdit ? url('/ministerios/' . (int) $m['id']) : url('/ministerios') ?>" class="card" novalidate>
      <?= Csrf::field() ?>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label required" for="name">Nome</label>
          <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" required>
          <?= field_error('name') ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-control<?= invalid('description') ?>" id="description" name="description" rows="2" maxlength="500"><?= e($val('description')) ?></textarea>
          <?= field_error('description') ?>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="active" value="1" id="active"<?= checked((string) $val('active', '1') === '1') ?>>
          <label class="form-check-label" for="active">Ativo</label>
        </div>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button></div>
    </form>
  </div>
  <?php if ($isEdit): ?>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header bg-white fw-semibold">Pessoas vinculadas</div>
      <?php if (!$members): ?>
        <div class="card-body small text-muted">Ninguém vinculado. O vínculo é feito no cadastro de cada pessoa.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush small">
          <?php foreach ($members as $p): ?>
            <li class="list-group-item d-flex justify-content-between">
              <a href="<?= url('/usuarios/' . (int) $p['id']) ?>"><?= e($p['name']) ?></a>
              <?php if ($p['is_leader']): ?><span class="badge text-bg-primary">líder</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
