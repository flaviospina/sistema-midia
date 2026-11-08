<?php $scripts = ['upload.js']; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= url('/') ?>">Álbuns</a></li><li class="breadcrumb-item"><a href="<?= url('/albuns/' . (int) $a['id']) ?>"><?= e($a['name']) ?></a></li><li class="breadcrumb-item active">Enviar fotos</li></ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Enviar fotos</h1><div class="text-muted small"><span class="font-monospace"><?= e($a['folder']) ?></span> · <?= (int) $a['photos_count'] ?> foto(s) já na pasta</div></div>
  <div class="d-flex gap-2"><a href="<?= url('/albuns/' . (int) $a['id'] . '/drive') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-google"></i> Importar do Drive</a><a href="<?= url('/albuns/' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-images"></i> Ver álbum</a></div>
</div>
<div id="uploader" data-album="<?= (int) $a['id'] ?>" data-endpoint="<?= url('/api/upload') ?>" data-chunk="<?= (int) $chunkBytes ?>" data-max="<?= (int) $maxBytes ?>" data-album-url="<?= url('/albuns/' . (int) $a['id']) ?>">
  <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Escolher fotos">
    <i class="bi bi-cloud-arrow-up fs-1 text-ouro d-block"></i>
    <div class="fw-semibold">Arraste as fotos aqui ou toque para escolher</div>
    <div class="small text-muted">JPG, PNG, WebP ou HEIC · até <?= e(format_bytes($maxBytes, 0)) ?> por foto · várias de uma vez · o envio continua de onde parou se a conexão cair</div>
    <input type="file" id="fileInput" accept="image/*,.heic,.heif" multiple>
  </div>
  <div class="d-flex gap-2 flex-wrap mt-3">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="pickCamera"><i class="bi bi-camera"></i> Câmera / galeria do celular</button>
    <button type="button" class="btn btn-sm btn-primary d-none" id="startBtn"><i class="bi bi-upload"></i> Enviar <span id="pendingCount"></span></button>
    <span class="ms-auto small text-muted align-self-center" id="overall"></span>
  </div>
  <div id="queue" class="mt-3"></div>
  <input type="file" id="cameraInput" accept="image/*" capture="environment" multiple class="d-none">
</div>
