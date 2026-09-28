<?php
$isEdit = $u !== null;
$val = static fn(string $k, $default = '') => old($k, $u[$k] ?? $default);
$minIds = array_map('intval', array_column($userMin, 'id'));
$leadIds = array_map('intval', array_column(array_filter($userMin, static fn($m) => (int) $m['is_leader'] === 1), 'id'));
$oldMin = old('ministries', null); $oldLead = old('leader_of', null);
if (is_array($oldMin)) { $minIds = array_map('intval', $oldMin); $leadIds = is_array($oldLead) ? array_map('intval', $oldLead) : []; }
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/usuarios/' . (int) $u['id']) : url('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>

<form method="post" action="<?= $isEdit ? url('/usuarios/' . (int) $u['id']) : url('/usuarios') ?>" enctype="multipart/form-data" novalidate>
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Identificação</div>
        <div class="card-body row g-3">
          <div class="col-12">
            <label class="form-label required" for="name">Nome completo</label>
            <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="150" required>
            <?= field_error('name') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label required" for="email">E-mail</label>
            <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e($val('email')) ?>" required>
            <?= field_error('email') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="whatsapp">WhatsApp</label>
            <input type="tel" class="form-control<?= invalid('whatsapp') ?>" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp', $u ? format_phone($u['whatsapp']) : '')) ?>" data-mask="phone" placeholder="(11) 98888-7777">
            <?= field_error('whatsapp') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label required" for="role">Perfil de acesso</label>
            <select class="form-select<?= invalid('role') ?>" id="role" name="role" required>
              <option value="">Selecione…</option>
              <?php foreach (Auth::ROLES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= selected($k, $val('role')) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= field_error('role') ?>
          </div>
        </div>
      </div>

      <div class="card mb-3" id="mediaFields">
        <div class="card-header bg-white fw-semibold">Equipe de mídia</div>
        <div class="card-body row g-3">
          <div class="col-md-6">
            <label class="form-label" for="member_status">Situação</label>
            <select class="form-select" id="member_status" name="member_status">
              <option value="ativo"<?= selected('ativo', $val('member_status', 'ativo')) ?>>Ativo</option>
              <option value="em_treinamento"<?= selected('em_treinamento', $val('member_status')) ?>>Em treinamento</option>
              <option value="afastado"<?= selected('afastado', $val('member_status')) ?>>Afastado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="joined_at">Data de entrada</label>
            <input type="date" class="form-control<?= invalid('joined_at') ?>" id="joined_at" name="joined_at" value="<?= e($val('joined_at')) ?>">
            <?= field_error('joined_at') ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="notes">Observações</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000"><?= e($val('notes')) ?></textarea>
          </div>
          <?php if ($isEdit): ?>
            <div class="col-12 small text-muted">As funções (som, projeção…) são definidas em <a href="<?= url('/usuarios/' . (int) $u['id'] . '/funcoes') ?>">Funções</a>.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Ministérios</div>
        <div class="card-body">
          <?php if (!$ministries): ?>
            <div class="text-muted small">Nenhum ministério cadastrado. <a href="<?= url('/ministerios/novo') ?>">Cadastrar</a></div>
          <?php endif; ?>
          <div class="row g-2">
          <?php foreach ($ministries as $m): $mid = (int) $m['id']; ?>
            <div class="col-md-6 d-flex align-items-center gap-3" data-ministry-row>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="ministries[]" value="<?= $mid ?>" id="min<?= $mid ?>"<?= checked(in_array($mid, $minIds, true)) ?>>
                <label class="form-check-label" for="min<?= $mid ?>"><?= e($m['name']) ?></label>
              </div>
              <div class="form-check mb-0 small">
                <input class="form-check-input" type="checkbox" name="leader_of[]" value="<?= $mid ?>" id="lead<?= $mid ?>"<?= checked(in_array($mid, $leadIds, true)) ?>>
                <label class="form-check-label text-muted" for="lead<?= $mid ?>">líder</label>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Foto</div>
        <div class="card-body text-center">
          <div id="photoPreview" class="mb-2"><?php partial('avatar', ['u' => $u ?? ['id' => 0, 'name' => $val('name') ?: '?', 'photo_path' => null], 'size' => 96]) ?></div>
          <input type="file" class="form-control form-control-sm" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-mb="<?= PHOTO_MAX_MB ?>">
          <div class="form-text">JPG, PNG ou WebP até <?= PHOTO_MAX_MB ?> MB. A foto é recortada e os dados de localização são removidos.</div>
          <?php if ($isEdit && $u['photo_path']): ?>
            <div class="form-check mt-2 text-start">
              <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
              <label class="form-check-label small" for="remove_photo">Remover foto atual</label>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!$isEdit): ?>
        <div class="alert alert-info small">Uma <strong>senha temporária</strong> será gerada e exibida após salvar. A pessoa deverá trocá-la no primeiro acesso.</div>
      <?php endif; ?>
      <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </div>
</form>
