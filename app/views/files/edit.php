<?php $fid = (int) $f['id']; $val = static fn(string $k, $d = '') => old($k, $f[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Editar arquivo</h1>
  <a href="<?= url('/arquivos/' . $fid) ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= url('/arquivos/' . $fid) ?>" class="row g-3" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body row g-3">
        <div class="col-12 text-muted small"><i class="bi <?= FileTypes::icon($f['category'], $f['extension']) ?>"></i> <?= e($f['original_name']) ?> · <?= e(format_bytes((int) $f['size_bytes'])) ?></div>
        <div class="col-md-8">
          <label class="form-label" for="title">Título</label>
          <input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="200"><?= field_error('title') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="category">Categoria</label>
          <select class="form-select" id="category" name="category"><?php foreach (FileTypes::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('category')) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-md-8">
          <label class="form-label required" for="folder_id">Pasta</label>
          <select class="form-select<?= invalid('folder_id') ?>" id="folder_id" name="folder_id">
            <?php if ($f['status'] !== 'aprovado'): ?><option value="">— sem pasta (quarentena) —</option><?php endif; ?>
            <?php foreach ($folders as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected($id, $val('folder_id')) ?>><?= e($label) ?></option><?php endforeach; ?>
          </select><?= field_error('folder_id') ?>
        </div>
        <?php if (Auth::can('files.moderate')): ?>
        <div class="col-md-4">
          <label class="form-label" for="visibility">Visibilidade</label>
          <select class="form-select" id="visibility" name="visibility"><option value="">Herdar da pasta</option>
            <?php foreach (Folder::VISIBILITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('visibility')) ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-md-6"><label class="form-label" for="event_id">Evento</label>
          <select class="form-select" id="event_id" name="event_id"><option value="">— nenhum —</option>
            <?php $found = false; foreach ($events as $ev): $found = $found || (int) $ev['id'] === (int) $f['event_id']; ?><option value="<?= (int) $ev['id'] ?>"<?= selected($ev['id'], old('event_id', $f['event_id'])) ?>><?= e(Event::label($ev)) ?></option><?php endforeach; ?>
            <?php if ($f['event_id'] && !$found): ?><option value="<?= (int) $f['event_id'] ?>" selected><?= e($f['event_name']) ?></option><?php endif; ?>
          </select><?php if ($f['event_name'] && !$f['event_id']): ?><div class="form-text">Evento informado no envio: "<?= e($f['event_name']) ?>"</div><input type="hidden" name="event" value="<?= e($f['event_name']) ?>"><?php endif; ?></div>
        <div class="col-md-6"><label class="form-label" for="tags">Tags</label><input type="text" class="form-control" id="tags" name="tags" value="<?= e(old('tags', Tag::textFor($f['tags']))) ?>" placeholder="separadas por vírgula"></div>
        <div class="col-12"><label class="form-label" for="description">Descrição</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="2000"><?= e($val('description')) ?></textarea></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <?php if (Auth::can('restrictions.view')): ?>
    <div class="card mb-3 border-danger-subtle">
      <div class="card-header bg-white fw-semibold">Direito de imagem</div>
      <div class="card-body small">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="has_restriction" value="1" id="has_restriction"<?= checked((string) $val('has_restriction') === '1') ?>>
          <label class="form-check-label" for="has_restriction">Contém pessoa com restrição de imagem <span class="text-muted">(fica restrito a administradores)</span></label>
        </div>
        <?php if ($restrictions): ?>
          <div class="text-muted mb-1">Quem aparece:</div>
          <?php foreach ($restrictions as $r): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="restrictions[]" value="<?= (int) $r['id'] ?>" id="r<?= (int) $r['id'] ?>"<?= checked(in_array((int) $r['id'], $linked, true)) ?>><label class="form-check-label" for="r<?= (int) $r['id'] ?>"><?= e($r['person_name']) ?><?= $r['is_minor'] ? ' (menor)' : '' ?></label></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
