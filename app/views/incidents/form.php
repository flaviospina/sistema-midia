<?php $isEdit = $i !== null; $val = static fn(string $k, $d = '') => old($k, $i[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/ocorrencias/' . (int) $i['id']) : url('/ocorrencias') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/ocorrencias/' . (int) $i['id']) : url('/ocorrencias') ?>" class="row g-3" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-8"><div class="card"><div class="card-body row g-3">
    <div class="col-12"><label class="form-label required" for="title">O que aconteceu (resumo)</label><input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="150" required placeholder="Ex.: Microfone 2 falhou durante o louvor"><?= field_error('title') ?></div>
    <div class="col-md-4"><label class="form-label" for="kind">Tipo</label><select class="form-select" id="kind" name="kind"><?php foreach (Incident::KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('kind', 'tecnico')) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label" for="severity">Gravidade</label><select class="form-select" id="severity" name="severity"><?php foreach (Incident::SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $val('severity', 'media')) ?>><?= e($l) ?></option><?php endforeach; ?></select><div class="form-text">Alta avisa a coordenação na hora.</div></div>
    <div class="col-md-4"><label class="form-label" for="event_id">Evento</label><select class="form-select<?= invalid('event_id') ?>" id="event_id" name="event_id"><option value="">— nenhum —</option><?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= selected($ev['id'], old('event_id', $eventId ?? '')) ?>><?= e(Event::label($ev)) ?></option><?php endforeach; ?></select><?= field_error('event_id') ?></div>
    <div class="col-md-6"><label class="form-label" for="equipment_id">Equipamento envolvido</label><select class="form-select<?= invalid('equipment_id') ?>" id="equipment_id" name="equipment_id"><option value="">— nenhum —</option><?php foreach ($equipment as $q): ?><option value="<?= (int) $q['id'] ?>"<?= selected($q['id'], old('equipment_id', $i['equipment_id'] ?? (ctype_digit(query('equipamento')) ? query('equipamento') : ''))) ?>><?= e($q['code'] . ' · ' . $q['name']) ?></option><?php endforeach; ?></select><?= field_error('equipment_id') ?></div>
    <div class="col-12"><label class="form-label" for="description">Detalhes</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="5000" placeholder="Quando, como, o que foi tentado, quem estava envolvido."><?= e($val('description')) ?></textarea></div>
  </div></div></div>
  <div class="col-lg-4">
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar</button>
  </div>
</form>
