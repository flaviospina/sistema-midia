<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Checklist de identidade visual</h1>
  <a href="<?= url('/artes') ?>" class="btn btn-sm btn-outline-secondary">Voltar</a>
</div>
<p class="small text-muted">A equipe marca estes itens antes de aprovar uma arte. Itens desmarcados como "ativo" somem das próximas aprovações mas o histórico é preservado.</p>
<form method="post" action="<?= url('/artes/checklist') ?>" class="card narrow-card"><?= Csrf::field() ?>
  <div class="card-body" id="checklistRows">
    <?php $rows = array_merge($items, [['id' => '', 'label' => '', 'sort_order' => 999, 'active' => 1], ['id' => '', 'label' => '', 'sort_order' => 999, 'active' => 1]]); foreach ($rows as $i => $it): ?>
      <div class="d-flex gap-1 align-items-center mb-2">
        <input type="hidden" name="items[<?= $i ?>][id]" value="<?= (int) $it['id'] ?: '' ?>">
        <input type="number" name="items[<?= $i ?>][sort_order]" class="form-control form-control-sm w-70" value="<?= (int) $it['sort_order'] ?>" aria-label="Ordem">
        <input type="text" name="items[<?= $i ?>][label]" class="form-control form-control-sm" value="<?= e($it['label']) ?>" maxlength="150" placeholder="<?= $it['id'] ? '' : 'Novo item…' ?>">
        <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= checked((int) $it['active'] === 1) ?> title="Ativo"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card-footer bg-white text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Salvar</button></div>
</form>
