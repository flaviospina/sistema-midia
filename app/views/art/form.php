<?php $isEdit = $r !== null; $val = static fn(string $k, $d = '') => old($k, $r[$k] ?? $d);
$oldFormats = old('formats', null); $fmts = is_array($oldFormats) ? $oldFormats : ($isEdit ? ArtRequest::formatsOf($r) : []); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/artes/' . (int) $r['id']) : url('/artes') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/artes/' . (int) $r['id']) : url('/artes') ?>" class="row g-3" novalidate>
  <?= Csrf::field() ?>
  <div class="col-lg-8"><div class="card"><div class="card-body row g-3">
    <div class="col-12"><label class="form-label required" for="title">Título da arte</label><input type="text" class="form-control<?= invalid('title') ?>" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="150" placeholder="Ex.: Congresso de Jovens 2026 — divulgação" required><?= field_error('title') ?></div>
    <div class="col-md-6"><label class="form-label<?= Auth::is('lider_ministerio') ? ' required' : '' ?>" for="ministry_id">Ministério solicitante</label>
      <select class="form-select<?= invalid('ministry_id') ?>" id="ministry_id" name="ministry_id"><option value="">— Igreja / geral —</option><?php foreach ($ministries as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], $val('ministry_id', count($ministries) === 1 ? $ministries[0]['id'] : '')) ?>><?= e($m['name']) ?></option><?php endforeach; ?></select><?= field_error('ministry_id') ?></div>
    <div class="col-md-6"><label class="form-label" for="event_id">Evento vinculado</label>
      <select class="form-select<?= invalid('event_id') ?>" id="event_id" name="event_id"><option value="">— nenhum —</option><?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= selected($ev['id'], $val('event_id')) ?>><?= e(Event::label($ev)) ?></option><?php endforeach; ?></select><?= field_error('event_id') ?></div>
    <div class="col-12"><label class="form-label required" for="briefing">Briefing</label><textarea class="form-control<?= invalid('briefing') ?>" id="briefing" name="briefing" rows="5" maxlength="5000" placeholder="O que é, para quem, tom, referências, o que não pode faltar…" required><?= e($val('briefing')) ?></textarea><?= field_error('briefing') ?></div>
    <div class="col-12"><label class="form-label" for="texts">Textos que devem constar</label><textarea class="form-control" id="texts" name="texts" rows="3" maxlength="5000" placeholder="Título, data, horário, local, preletor, versículo, inscrições…"><?= e($val('texts')) ?></textarea></div>
  </div></div></div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Formatos e prazo</div><div class="card-body">
      <div class="mb-3"><?= field_error('formats') ?>
        <?php foreach (ArtRequest::FORMATS as $k => $l): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="formats[]" value="<?= e($k) ?>" id="fmt_<?= e($k) ?>"<?= checked(in_array($k, $fmts, true)) ?>><label class="form-check-label" for="fmt_<?= e($k) ?>"><?= e($l) ?><?= in_array($k, ART_PASTORAL_FORMATS, true) ? ' <span class="text-muted small">(aprovação pastoral)</span>' : '' ?></label></div>
        <?php endforeach; ?>
      </div>
      <label class="form-label required" for="publish_on">Data de publicação</label>
      <input type="date" class="form-control<?= invalid('publish_on') ?>" id="publish_on" name="publish_on" value="<?= e($val('publish_on', date('Y-m-d', strtotime('+' . ART_MIN_DAYS . ' days')))) ?>" min="<?= date('Y-m-d') ?>" required><?= field_error('publish_on') ?>
      <div class="form-text">Prazo mínimo da equipe: <?= ART_MIN_DAYS ?> dias. Abaixo disso o pedido entra como <strong>urgente</strong> e pode não ser atendido a tempo.</div>
      <?php if ($isEdit && Auth::can('art.manage')): ?>
        <label class="form-label mt-3" for="needs_pastoral_override">Aprovação pastoral</label>
        <select class="form-select form-select-sm" id="needs_pastoral_override" name="needs_pastoral_override"><option value="">Automática (pelos formatos)</option><option value="1"<?= selected('1', old('needs_pastoral_override')) ?>>Exigir</option><option value="0"<?= selected('0', old('needs_pastoral_override')) ?>>Dispensar</option></select>
      <?php endif; ?>
    </div></div>
    <button class="btn btn-primary w-100"><i class="bi bi-send"></i> <?= $isEdit ? 'Salvar' : 'Enviar pedido' ?></button>
    <?php if (!$isEdit): ?><div class="form-text mt-2">Depois de enviar, você pode anexar referências e arquivos na página do pedido.</div><?php endif; ?>
  </div>
</form>
