<?php $isEdit = $r !== null; $val = static fn(string $k, $d = '') => old($k, $r[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= url('/restricoes') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/restricoes/' . (int) $r['id']) : url('/restricoes') ?>" enctype="multipart/form-data" class="row g-3" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-7">
    <div class="card"><div class="card-body row g-3">
      <div class="col-12"><label class="form-label required" for="person_name">Nome da pessoa</label><input type="text" class="form-control<?= invalid('person_name') ?>" id="person_name" name="person_name" value="<?= e($val('person_name')) ?>" maxlength="150" required><?= field_error('person_name') ?></div>
      <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_minor" value="1" id="is_minor"<?= checked((string) $val('is_minor') === '1') ?>><label class="form-check-label" for="is_minor">É menor de idade</label></div>
      <div class="col-md-6"><label class="form-label" for="guardian_name">Responsável (obrigatório para menores)</label><input type="text" class="form-control<?= invalid('guardian_name') ?>" id="guardian_name" name="guardian_name" value="<?= e($val('guardian_name')) ?>" maxlength="150"><?= field_error('guardian_name') ?></div>
      <div class="col-md-6"><label class="form-label" for="contact">Contato</label><input type="text" class="form-control" id="contact" name="contact" value="<?= e($val('contact')) ?>" maxlength="100" placeholder="WhatsApp ou e-mail"></div>
      <div class="col-12"><label class="form-label" for="notes">Observações</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Ex.: não autoriza transmissão ao vivo nem redes sociais; fotos internas do culto são permitidas."><?= e($val('notes')) ?></textarea></div>
      <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="active" value="1" id="active"<?= checked((string) $val('active', '1') === '1') ?>><label class="form-check-label" for="active">Restrição ativa</label></div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Foto de referência</div>
      <div class="card-body text-center">
        <div id="photoPreview" class="mb-2"><?php if ($isEdit && $r['photo_path']): ?><img src="<?= url('/restricoes/' . (int) $r['id'] . '/foto') ?>" class="avatar avatar-96 rounded-circle" alt=""><?php else: ?><span class="avatar avatar-initials avatar-96 rounded-circle"><?= e(initials($val('person_name') ?: '?')) ?></span><?php endif; ?></div>
        <input type="file" class="form-control form-control-sm" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-mb="<?= PHOTO_MAX_MB ?>">
        <div class="form-text">Visível apenas à equipe de mídia, para reconhecimento na hora de selecionar fotos.</div>
        <?php if ($isEdit && $r['photo_path']): ?><div class="form-check mt-2 text-start"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label small" for="remove_photo">Remover foto</label></div><?php endif; ?>
      </div>
    </div>
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
