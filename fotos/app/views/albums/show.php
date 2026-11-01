<?php $scripts = ['gallery.js']; $aid = (int) $a['id']; $canManage = Auth::can('albums.manage'); $openId = ctype_digit(query('foto')) ? (int) query('foto') : 0; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= url('/', ['ano' => substr($a['event_date'], 0, 4)]) ?>">Álbuns</a></li><li class="breadcrumb-item active"><?= e($a['name']) ?></li></ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e($a['name']) ?> <?php if ($a['hidden']): ?><span class="badge text-bg-secondary" title="Só a equipe vê"><i class="bi bi-eye-slash"></i> oculto</span><?php endif; ?></h1>
    <div class="text-muted small"><i class="bi bi-calendar-event"></i> <?= e(weekday_pt($a['event_date'])) ?>, <?= e(format_date($a['event_date'])) ?> · <?= count($photos) ?> foto(s)<?= $versions ? ' + ' . count($versions) . ' editada(s)' : '' ?> · <?= e(format_bytes((int) $a['size_bytes'])) ?><?= $a['description'] ? ' · ' . e($a['description']) : '' ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (Auth::can('photos.upload')): ?><a href="<?= url('/albuns/' . $aid . '/enviar') ?>" class="btn btn-sm btn-primary"><i class="bi bi-cloud-arrow-up"></i> Enviar fotos</a><?php endif; ?>
    <?php if ($zipOk && $photos && Auth::can('photos.download')): ?><form method="post" action="<?= url('/albuns/' . $aid . '/zip') ?>" id="zipForm"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary" id="zipBtn"><i class="bi bi-file-earmark-zip"></i> Baixar tudo (ZIP)</button></form><?php endif; ?>
    <?php if ($canManage): ?><a href="<?= url('/albuns/' . $aid . '/editar') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
      <?php if (!$photos || Auth::is('admin')): ?><form method="post" action="<?= url('/albuns/' . $aid . '/excluir') ?>" data-confirm="Excluir esta pasta<?= $photos ? ' e TODAS as suas fotos' : '' ?>? Esta ação não pode ser desfeita." data-confirm-type="danger"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php if (!$photos): ?>
  <div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-image fs-1 d-block mb-2"></i>Esta pasta ainda não tem fotos.<?php if (Auth::can('photos.upload')): ?><br><a href="<?= url('/albuns/' . $aid . '/enviar') ?>" class="btn btn-primary btn-sm mt-3">Enviar fotos</a><?php endif; ?></div></div>
<?php else: ?>
<div class="d-flex align-items-center gap-2 mb-2 small">
  <?php if (Auth::can('photos.download')): ?><div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="selectAll"><label class="form-check-label" for="selectAll">Selecionar todas</label></div>
  <span class="text-muted" id="selCount"></span><?php endif; ?>
  <span class="ms-auto text-muted">Toque numa foto para ampliar</span>
</div>
<div class="photo-grid" id="gallery" data-album="<?= $aid ?>" data-can-delete="<?= Auth::can('photos.delete') ? 1 : 0 ?>" data-can-share="<?= Auth::can('social.share') ? 1 : 0 ?>" data-can-download="<?= Auth::can('photos.download') ? 1 : 0 ?>" data-open="<?= $openId ?>">
  <?php foreach ($photos as $p): $pid = (int) $p['id']; ?>
    <figure class="photo-card" data-id="<?= $pid ?>" data-name="<?= e($p['file_name']) ?>" data-caption="<?= e($p['caption'] ?? '') ?>" data-src="<?= url('/fotos/' . $pid . '/imagem') ?>" data-download="<?= url('/fotos/' . $pid . '/original') ?>" data-edit="<?= url('/fotos/' . $pid . '/melhorar') ?>" data-share="<?= url('/fotos/' . $pid . '/compartilhar') ?>" data-delete="<?= url('/fotos/' . $pid . '/excluir') ?>" data-mine="<?= (int) $p['uploaded_by'] === Auth::id() ? 1 : 0 ?>" data-meta="<?= e(($p['width'] ? $p['width'] . '×' . $p['height'] . ' · ' : '') . format_bytes((int) $p['size_bytes']) . ($p['taken_at'] ? ' · ' . format_datetime($p['taken_at']) : '') . ' · ' . ($p['uploader_name'] ?? '')) ?>">
      <?php if (Auth::can('photos.download')): ?><input type="checkbox" class="form-check-input photo-select" name="ids[]" value="<?= $pid ?>" form="zipForm" aria-label="Selecionar"><?php endif; ?>
      <img src="<?= url('/fotos/' . $pid . '/miniatura') ?>" alt="<?= e($p['caption'] ?? $p['file_name']) ?>" loading="lazy">
      <?php if ((int) $a['cover_photo_id'] === $pid): ?><span class="photo-badge" title="Capa"><i class="bi bi-star-fill"></i></span><?php endif; ?>
    </figure>
  <?php endforeach; ?>
</div>
<?php if ($versions): ?>
  <h2 class="h6 text-muted text-uppercase mt-4 mb-2">Versões editadas</h2>
  <div class="photo-grid photo-grid--sm" id="galleryVersions">
    <?php foreach ($versions as $p): $pid = (int) $p['id']; ?>
      <figure class="photo-card" data-id="<?= $pid ?>" data-name="<?= e($p['file_name']) ?>" data-caption="<?= e($p['edit_note'] ?? '') ?>" data-src="<?= url('/fotos/' . $pid . '/imagem') ?>" data-download="<?= url('/fotos/' . $pid . '/original') ?>" data-edit="<?= url('/fotos/' . $pid . '/melhorar') ?>" data-share="<?= url('/fotos/' . $pid . '/compartilhar') ?>" data-delete="<?= url('/fotos/' . $pid . '/excluir') ?>" data-mine="<?= (int) $p['uploaded_by'] === Auth::id() ? 1 : 0 ?>" data-meta="<?= e('editada por ' . ($p['uploader_name'] ?? '') . ' · ' . format_datetime($p['created_at'])) ?>">
        <img src="<?= url('/fotos/' . $pid . '/miniatura') ?>" alt="" loading="lazy"><span class="photo-badge photo-badge--edit" title="Editada"><i class="bi bi-magic"></i></span>
      </figure>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php endif; ?>

<!-- Visualizador -->
<div class="lightbox d-none" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto ampliada">
  <button type="button" class="lb-close" data-lb-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
  <button type="button" class="lb-nav lb-prev" data-lb-prev aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>
  <button type="button" class="lb-nav lb-next" data-lb-next aria-label="Próxima"><i class="bi bi-chevron-right"></i></button>
  <div class="lb-stage"><img id="lbImg" alt=""></div>
  <div class="lb-bar">
    <div class="lb-info min-w-0"><div class="lb-name text-truncate" id="lbName"></div><div class="lb-meta small" id="lbMeta"></div><div class="lb-caption small" id="lbCaption"></div></div>
    <div class="lb-actions">
      <a class="btn btn-sm btn-light" id="lbEdit" href="#"><i class="bi bi-magic"></i> Melhorar</a>
      <a class="btn btn-sm btn-light d-none" id="lbDownload" href="#" download><i class="bi bi-download"></i> Original</a>
      <button type="button" class="btn btn-sm btn-light d-none" id="lbShareNative"><i class="bi bi-share"></i> Compartilhar</button>
      <a class="btn btn-sm btn-light d-none" id="lbShare" href="#"><i class="bi bi-instagram"></i> Publicar</a>
      <?php if ($canManage): ?><form method="post" id="lbCoverForm" class="d-inline"><?= Csrf::field() ?><button class="btn btn-sm btn-light" title="Usar como capa"><i class="bi bi-star"></i></button></form><?php endif; ?>
      <form method="post" id="lbDeleteForm" class="d-inline d-none" data-confirm="Excluir esta foto do álbum?" data-confirm-type="danger"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-light" title="Excluir"><i class="bi bi-trash"></i></button></form>
    </div>
  </div>
</div>
