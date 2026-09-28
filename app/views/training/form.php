<?php $isEdit = $t !== null; $val = static fn(string $k, $d = '') => old($k, $t[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= url('/capacitacao/trilhas') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/capacitacao/trilhas/' . (int) $t['id']) : url('/capacitacao/trilhas') ?>" class="row g-3" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-8"><div class="card"><div class="card-body row g-3">
    <div class="col-md-8"><label class="form-label required" for="title">Título</label><input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="150" required placeholder="Ex.: Operar a mesa de som no culto"><?= field_error('title') ?></div>
    <div class="col-md-4"><label class="form-label required" for="function_id">Função</label><select class="form-select<?= invalid('function_id') ?>" id="function_id" name="function_id"><option value="">— escolha —</option><?php foreach ($functions as $f): ?><option value="<?= (int) $f['id'] ?>"<?= selected($f['id'], old('function_id', $functionId ?? '')) ?>><?= e($f['name']) ?></option><?php endforeach; ?></select><?= field_error('function_id') ?></div>
    <div class="col-12"><label class="form-label" for="description">Descrição / o que a pessoa precisa demonstrar</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"><?= e($val('description')) ?></textarea></div>
    <div class="col-md-8"><label class="form-label" for="resource_url">Link do material (vídeo, PDF, drive)</label><input type="url" class="form-control<?= invalid('resource_url') ?>" id="resource_url" name="resource_url" value="<?= e($val('resource_url')) ?>" maxlength="300" placeholder="https://"><?= field_error('resource_url') ?></div>
    <div class="col-md-4"><label class="form-label" for="file_id">Arquivo do repositório (ID)</label><input type="number" class="form-control<?= invalid('file_id') ?>" id="file_id" name="file_id" value="<?= e($val('file_id')) ?>" min="1" placeholder="Opcional"><?= field_error('file_id') ?><div class="form-text">O número aparece no endereço do arquivo em Arquivos.</div></div>
    <div class="col-md-3"><label class="form-label" for="sort_order">Ordem</label><input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= e($val('sort_order', '10')) ?>" min="0" max="999"></div>
    <div class="col-md-4 pt-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="required" value="1" id="required"<?= checked((bool) old('required', $t['required'] ?? 1)) ?>><label class="form-check-label" for="required">Obrigatório para ficar apto</label></div></div>
    <div class="col-md-4 pt-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active"<?= checked((bool) old('active', $t['active'] ?? 1)) ?>><label class="form-check-label" for="active">Ativo</label></div></div>
  </div></div></div>
  <div class="col-lg-4"><button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button></div>
</form>
