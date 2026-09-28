<?php /** @var int $maxBytes  @var string|null $metaFormId */ ?>
<div data-uploader
     data-endpoint="<?= url('/api/upload') ?>"
     data-chunk="<?= UPLOAD_CHUNK_BYTES ?>"
     data-max="<?= (int) $maxBytes ?>"
     data-allowed="<?= e(implode(',', FileTypes::allowedExtensions())) ?>"
     data-file-base="<?= url('/arquivos/') ?>"
     <?= !empty($metaFormId) ? 'data-meta-form="' . e($metaFormId) . '"' : '' ?>
     <?= !empty($reloadOnDone) ? 'data-reload-on-done' : '' ?>>
  <div class="dropzone" data-dropzone>
    <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
    <div class="fw-semibold">Arraste os arquivos aqui ou toque para escolher</div>
    <div class="small text-muted">Fotos, vídeos, áudios, PDF, documentos e artes · até <?= e(format_bytes((int) $maxBytes)) ?> por arquivo · envio retomável se a conexão cair</div>
    <label class="btn btn-primary btn-sm mt-2">
      <i class="bi bi-folder2-open"></i> Escolher arquivos
      <input type="file" multiple accept="<?= e(implode(',', array_map(static fn($e) => '.' . $e, FileTypes::allowedExtensions()))) ?>">
    </label>
  </div>
  <div class="small text-muted mt-2" data-summary></div>
  <div data-list></div>
</div>
