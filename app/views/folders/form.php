<?php $isEdit = $folder !== null; $val = static fn(string $k, $d = '') => old($k, $folder[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/pastas/' . (int) $folder['id']) : ($parentId ? url('/pastas/' . (int) $parentId) : url('/arquivos')) ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" action="<?= $isEdit ? url('/pastas/' . (int) $folder['id']) : url('/pastas') ?>" class="card" novalidate>
      <?= Csrf::field() ?>
      <div class="card-body row g-3">
        <div class="col-md-8">
          <label class="form-label required" for="name">Nome</label>
          <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" required><?= field_error('name') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="sort_order">Ordem</label>
          <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= e($val('sort_order', '0')) ?>" min="-9999" max="9999">
        </div>
        <div class="col-12">
          <label class="form-label" for="parent_id">Dentro de</label>
          <select class="form-select<?= invalid('parent_id') ?>" id="parent_id" name="parent_id"><option value="">— Raiz —</option>
            <?php foreach ($options as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected($id, old('parent_id', $parentId)) ?>><?= e($label) ?></option><?php endforeach; ?>
          </select><?= field_error('parent_id') ?>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="visibility">Visibilidade</label>
          <select class="form-select<?= invalid('visibility') ?>" id="visibility" name="visibility"><option value="">Herdar da pasta pai</option>
            <?php foreach (Folder::VISIBILITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('visibility')) ?>><?= e($l) ?></option><?php endforeach; ?>
          </select><?= field_error('visibility') ?>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ministry_id">Ministério dono</label>
          <select class="form-select<?= invalid('ministry_id') ?>" id="ministry_id" name="ministry_id"><option value="">— herdar / nenhum —</option>
            <?php foreach ($ministries as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], $val('ministry_id')) ?>><?= e($m['name']) ?></option><?php endforeach; ?>
          </select><?= field_error('ministry_id') ?>
          <div class="form-text">Com visibilidade "ministério", líderes deste ministério veem e enviam arquivos aqui.</div>
        </div>
        <div class="col-12"><label class="form-label" for="description">Descrição</label><input type="text" class="form-control" id="description" name="description" value="<?= e($val('description')) ?>" maxlength="500"></div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <?php if ($isEdit && !(int) $folder['is_system']): ?>
          <button class="btn btn-outline-danger btn-sm" form="delForm"><i class="bi bi-trash"></i> Excluir pasta</button>
        <?php else: ?><span></span><?php endif; ?>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
      </div>
    </form>
    <?php if ($isEdit): ?><form id="delForm" method="post" action="<?= url('/pastas/' . (int) $folder['id'] . '/excluir') ?>" data-confirm="Excluir esta pasta? Só é possível se estiver vazia."><?= Csrf::field() ?></form><?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-body small">
      <div class="fw-semibold mb-1">Como funciona a visibilidade</div>
      <ul class="mb-0 ps-3">
        <li><strong>Restrito</strong>: só administradores.</li>
        <li><strong>Equipe de mídia</strong>: admin, coordenadores e membros da mídia.</li>
        <li><strong>Equipe + ministério</strong>: equipe de mídia e as pessoas vinculadas ao ministério dono.</li>
        <li><strong>Todos os usuários logados</strong>: inclui líderes, pastores e membros da igreja.</li>
      </ul>
      <div class="mt-2 text-muted">Subpastas herdam a visibilidade e o ministério da pasta pai; um arquivo pode sobrescrever a visibilidade da sua pasta. Arquivos com restrição de imagem ficam sempre restritos.</div>
    </div></div>
  </div>
</div>
