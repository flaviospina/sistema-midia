<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Checklist pré-culto</h1><div class="text-muted small">Itens que cada função confere antes do culto. Deixe o texto vazio para remover um item.</div></div>
</div>
<div class="row g-3">
<?php foreach ($functions as $f): $fid = (int) $f['id']; $items = $byFunction[$fid]['items'] ?? []; ?>
  <div class="col-md-6 col-xl-4">
    <form method="post" action="<?= url('/checklist/' . $fid) ?>" class="card h-100" data-repeat><?= Csrf::field() ?>
      <div class="card-header bg-white fw-semibold"><?= e($f['name']) ?></div>
      <div class="card-body p-2" data-rows>
        <?php $n = 0; foreach ($items as $it): if (!$it['active']) continue; ?>
          <div class="input-group input-group-sm mb-1" data-row>
            <input type="hidden" name="items[<?= $n ?>][id]" value="<?= (int) $it['id'] ?>">
            <input type="number" name="items[<?= $n ?>][sort_order]" value="<?= (int) $it['sort_order'] ?>" class="form-control w-70 flex-grow-0" min="0" max="999" aria-label="Ordem">
            <input type="text" name="items[<?= $n ?>][label]" value="<?= e($it['label']) ?>" class="form-control" maxlength="150" aria-label="Item">
          </div>
        <?php $n++; endforeach; ?>
        <template data-row-template>
          <div class="input-group input-group-sm mb-1" data-row>
            <input type="number" name="items[__N__][sort_order]" value="<?= ($n + 1) * 10 ?>" class="form-control w-70 flex-grow-0" min="0" max="999" aria-label="Ordem">
            <input type="text" name="items[__N__][label]" value="" class="form-control" maxlength="150" placeholder="Novo item" aria-label="Item">
          </div>
        </template>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-row><i class="bi bi-plus"></i> Item</button>
        <button class="btn btn-sm btn-primary">Salvar</button>
      </div>
    </form>
  </div>
<?php endforeach; ?>
</div>
