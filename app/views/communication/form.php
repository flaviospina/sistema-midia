<?php $isEdit = $p !== null; $val = static fn(string $k, $d = '') => old($k, $p[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= url('/comunicacao', ['mes' => substr($date, 0, 7)]) ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/comunicacao/' . (int) $p['id']) : url('/comunicacao') ?>" class="card narrow-card" novalidate><?= Csrf::field() ?>
  <div class="card-body row g-3">
    <div class="col-12"><label class="form-label required" for="title">Título</label><input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="150" required><?= field_error('title') ?></div>
    <div class="col-6"><label class="form-label" for="channel">Canal</label><select class="form-select" id="channel" name="channel"><?php foreach (Publication::CHANNELS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('channel', 'instagram')) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-3"><label class="form-label required" for="date">Data</label><input type="date" class="form-control<?= invalid('date') ?>" id="date" name="date" value="<?= e(old('date', $date)) ?>" required><?= field_error('date') ?></div>
    <div class="col-3"><label class="form-label" for="time">Hora</label><input type="time" class="form-control<?= invalid('time') ?>" id="time" name="time" value="<?= e(old('time', $isEdit ? substr($p['publish_at'], 11, 5) : '09:00')) ?>"><?= field_error('time') ?></div>
    <div class="col-12"><label class="form-label" for="event_id">Evento</label><select class="form-select" id="event_id" name="event_id"><option value="">— nenhum —</option><?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= selected($ev['id'], $val('event_id')) ?>><?= e(Event::label($ev)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label" for="responsible_id">Responsável</label><select class="form-select" id="responsible_id" name="responsible_id"><option value="">—</option><?php foreach ($team as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $val('responsible_id')) ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label" for="file_id">Arquivo (id no repositório)</label><input type="number" class="form-control<?= invalid('file_id') ?>" id="file_id" name="file_id" value="<?= e($val('file_id')) ?>" min="1" placeholder="opcional"><?= field_error('file_id') ?></div>
    <div class="col-12"><label class="form-label" for="notes">Observações</label><input type="text" class="form-control" id="notes" name="notes" value="<?= e($val('notes')) ?>" maxlength="500" placeholder="Legenda, hashtags, quem posta…"></div>
  </div>
  <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button></div>
</form>
