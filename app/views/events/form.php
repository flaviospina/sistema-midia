<?php $isEdit = $e !== null; $val = static fn(string $k, $d = '') => old($k, $e[$k] ?? $d);
$duration = $isEdit && $e['ends_at'] ? (int) ((strtotime($e['ends_at']) - strtotime($e['starts_at'])) / 60) : 120; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/eventos/' . (int) $e['id']) : url('/eventos', ['mes' => substr($date, 0, 7)]) ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/eventos/' . (int) $e['id']) : url('/eventos') ?>" class="row g-3" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-8"><div class="card"><div class="card-body row g-3">
    <div class="col-md-8"><label class="form-label required" for="title">Nome</label><input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="150" required><?= field_error('title') ?></div>
    <div class="col-md-4"><label class="form-label" for="event_type">Tipo</label><select class="form-select" id="event_type" name="event_type"><?php foreach (Event::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('event_type', 'culto')) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label required" for="date">Data</label><input type="date" class="form-control<?= invalid('date') ?>" id="date" name="date" value="<?= e(old('date', $date)) ?>" required><?= field_error('date') ?></div>
    <div class="col-md-4"><label class="form-label required" for="time">Horário</label><input type="time" class="form-control<?= invalid('time') ?>" id="time" name="time" value="<?= e(old('time', $isEdit ? substr($e['starts_at'], 11, 5) : '19:00')) ?>" required><?= field_error('time') ?></div>
    <div class="col-md-4"><label class="form-label" for="duration">Duração (min)</label><input type="number" class="form-control<?= invalid('duration') ?>" id="duration" name="duration" value="<?= e(old('duration', (string) $duration)) ?>" min="15" max="1440"><?= field_error('duration') ?></div>
    <div class="col-md-6"><label class="form-label" for="location">Local</label><input type="text" class="form-control" id="location" name="location" value="<?= e($val('location')) ?>" maxlength="150" placeholder="Templo, salão, online…"></div>
    <div class="col-md-6"><label class="form-label" for="ministry_id">Ministério responsável</label><select class="form-select" id="ministry_id" name="ministry_id"><option value="">— Igreja / geral —</option><?php foreach ($ministries as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], $val('ministry_id')) ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label" for="description">Descrição</label><textarea class="form-control" id="description" name="description" rows="2" maxlength="3000"><?= e($val('description')) ?></textarea></div>
    <div class="col-12"><label class="form-label" for="notes">Observações para a equipe</label><textarea class="form-control" id="notes" name="notes" rows="2" maxlength="3000" placeholder="Ex.: chegar 1h antes; haverá batismo; transmitir no YouTube."><?= e($val('notes')) ?></textarea></div>
  </div></div></div>
  <div class="col-lg-4">
    <?php if (!$isEdit): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Vagas da escala</div><div class="card-body">
      <label class="form-label small" for="template_id">Aplicar modelo</label>
      <select class="form-select form-select-sm" id="template_id" name="template_id"><option value="">— definir depois —</option><?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], old('template_id', $t['name'] === 'Culto padrão' ? $t['id'] : '')) ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
      <div class="form-text">Você ajusta as quantidades na tela da escala.</div>
    </div></div>
    <?php endif; ?>
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
