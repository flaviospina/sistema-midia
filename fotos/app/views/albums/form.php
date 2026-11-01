<?php $isEdit = $a !== null; $val = static fn(string $k, $d = '') => old($k, $a[$k] ?? $d); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e($title) ?></h1>
  <a href="<?= $isEdit ? url('/albuns/' . (int) $a['id']) : url('/') ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>
<form method="post" action="<?= $isEdit ? url('/albuns/' . (int) $a['id']) : url('/albuns') ?>" class="row g-3" novalidate id="albumForm" data-suggest-url="<?= url('/albuns/sugestao') ?>">
  <?= Csrf::field() ?>
  <div class="col-lg-7">
    <div class="card"><div class="card-body row g-3">
      <?php if (!$isEdit): ?>
        <div class="col-md-5"><label class="form-label required" for="event_date">Data do culto / evento</label><input type="date" class="form-control<?= invalid('event_date') ?>" id="event_date" name="event_date" value="<?= e(old('event_date', $date)) ?>" required><?= field_error('event_date') ?><div class="form-text" id="weekdayHint"></div></div>
      <?php else: ?>
        <div class="col-md-5"><label class="form-label">Data</label><div class="form-control-plaintext"><?= e(format_date($a['event_date'])) ?> · <?= e(weekday_pt($a['event_date'])) ?></div></div>
      <?php endif; ?>
      <div class="col-md-7"><label class="form-label required" for="name">Nome da pasta</label><input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name', $a['name'] ?? ($suggested ?? ''))) ?>" maxlength="120" required placeholder="Ex.: Culto com a Família"><?= field_error('name') ?>
        <?php if (!$isEdit): ?><div class="form-text" id="suggestHint">Sugerido pelo dia da semana; você pode escrever outro nome.</div><?php endif; ?></div>
      <div class="col-12"><label class="form-label" for="description">Descrição (opcional)</label><input type="text" class="form-control" id="description" name="description" value="<?= e($val('description')) ?>" maxlength="500" placeholder="Pregador, tema, observações"></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="hidden" value="1" id="hidden"<?= checked((bool) old('hidden', $a['hidden'] ?? 0)) ?>><label class="form-check-label" for="hidden">Pasta oculta para membros (só a equipe de mídia vê)</label></div></div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <?php if (!$isEdit): ?>
    <div class="card border-primary mb-3"><div class="card-header fw-semibold"><i class="bi bi-folder2"></i> Pasta que será criada no servidor</div>
      <div class="card-body">
        <div class="folder-preview font-monospace" id="folderPreview">…</div>
        <div class="small text-muted mt-2">Formato: <em>AAAA-MM-DD Nome do evento</em>. Acentos e símbolos são removidos do nome da pasta; o nome completo fica guardado no sistema.</div>
        <div class="alert alert-warning py-2 small mt-2 d-none" id="existsHint"><i class="bi bi-exclamation-triangle"></i> Já existe uma pasta com esta data e nome. <a href="#" id="existsLink" class="alert-link">Abrir a existente</a> ou mude o nome.</div>
        <div class="form-check mt-3"><input class="form-check-input<?= invalid('confirm') ?>" type="checkbox" name="confirm" value="1" id="confirm" required><label class="form-check-label" for="confirm">Confirmo a criação da pasta com este nome</label><?= field_error('confirm') ?></div>
      </div></div>
    <div class="card mb-3"><div class="card-body small"><strong>Nomes padrão por dia da semana</strong><ul class="mb-0 mt-1 ps-3"><li>Domingo: Culto com a Família</li><li>Terça: Culto de Ensino</li><li>Quarta: Círculo de Oração</li><li>Quinta: Culto Público</li><li>Sábado: Culto dos Jovens e Adolescentes</li><li>Segunda e sexta: informe o nome do evento</li></ul></div></div>
    <?php else: ?>
    <div class="card mb-3"><div class="card-body small text-muted">Pasta no servidor: <span class="font-monospace"><?= e($a['folder']) ?></span><br>Renomear o álbum não muda a pasta física (evita quebrar links).</div></div>
    <?php endif; ?>
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Salvar' : 'Criar pasta e enviar fotos' ?></button>
  </div>
</form>
