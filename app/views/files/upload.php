<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Enviar arquivos</h1>
  <a href="<?= $folderId ? url('/pastas/' . $folderId) : url('/arquivos') ?>" class="btn btn-sm btn-outline-secondary">Voltar</a>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <form id="uploadMeta" class="card" onsubmit="return false">
      <div class="card-header bg-white fw-semibold">Dados dos arquivos</div>
      <div class="card-body">
        <?php if ($needsModeration): ?>
          <div class="alert alert-info small py-2">Seus envios vão para a <strong>quarentena</strong> e serão publicados após revisão da equipe de mídia.</div>
          <input type="hidden" name="folder_id" value="">
        <?php else: ?>
          <div class="mb-2">
            <label class="form-label small required" for="folder_id">Pasta de destino</label>
            <select name="folder_id" id="folder_id" class="form-select form-select-sm" required>
              <option value="">Quarentena (a equipe escolhe a pasta)</option>
              <?php foreach ($folders as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected($id, $folderId) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="mb-2"><label class="form-label small" for="title">Título (opcional, vale para todos)</label><input type="text" name="title" id="title" class="form-control form-control-sm" maxlength="200"></div>
        <div class="mb-2"><label class="form-label small" for="event_id">Evento</label>
          <select name="event_id" id="event_id" class="form-select form-select-sm"><option value="">— nenhum —</option>
            <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= selected($ev['id'], $eventId) ?>><?= e(Event::label($ev)) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-2"><label class="form-label small" for="tags">Tags (separadas por vírgula)</label><input type="text" name="tags" id="tags" class="form-control form-control-sm" placeholder="culto, jovens, louvor"></div>
        <div class="mb-2">
          <label class="form-label small" for="category">Categoria</label>
          <select name="category" id="category" class="form-select form-select-sm"><option value="">Automática (pelo tipo do arquivo)</option>
            <?php foreach (FileTypes::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php if (Auth::can('folders.manage')): ?>
        <div class="mb-2">
          <label class="form-label small" for="visibility">Visibilidade do arquivo</label>
          <select name="visibility" id="visibility" class="form-select form-select-sm"><option value="">Herdar da pasta</option>
            <?php foreach (Folder::VISIBILITIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="mb-0"><label class="form-label small" for="description">Descrição</label><textarea name="description" id="description" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
      </div>
      <div class="card-footer bg-white small text-muted">
        <?php if ($quota > 0): ?>
          Cota: <?= e(format_bytes($used)) ?> de <?= e(format_bytes($quota)) ?>
          <div class="progress usage-bar mt-1"><div class="progress-bar<?= $used / $quota > .9 ? ' bg-danger' : '' ?>" data-width="<?= (int) min(100, round($used / $quota * 100)) ?>"></div></div>
        <?php else: ?>Sem limite de cota para o seu perfil.<?php endif; ?>
      </div>
    </form>
  </div>
  <div class="col-lg-8">
    <?php partial('uploader', ['maxBytes' => UPLOAD_MAX_BYTES, 'metaFormId' => 'uploadMeta']) ?>
    <div class="small text-muted mt-2">
      Os dados do painel ao lado são aplicados a cada arquivo <em>no momento em que ele é adicionado</em>. Duplicados (mesmo conteúdo) são detectados antes de gravar.
      Fotos JPEG têm os dados de localização (EXIF/GPS) removidos da versão exibida.
    </div>
  </div>
</div>
