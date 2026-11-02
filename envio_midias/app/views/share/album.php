<div class="card publico-card mb-3">
  <div class="card-body">
    <h1 class="h4 mb-1"><?= e($l['album_name']) ?></h1>
    <div class="text-muted small"><i class="bi bi-calendar-event"></i> <?= e(weekday_pt($l['event_date'])) ?>, <?= e(format_date($l['event_date'])) ?> · <?= count($photos) ?> foto(s)<?= $l['album_description'] ? ' · ' . e($l['album_description']) : '' ?></div>
  </div>
  <div class="card-footer d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-sm btn-primary d-none" data-native-share data-title="<?= e($l['album_name']) ?>" data-url="<?= e($canonical) ?>"><i class="bi bi-share"></i> Compartilhar</button>
    <a class="btn btn-sm btn-outline-secondary" href="https://wa.me/?text=<?= rawurlencode($l['album_name'] . ' ' . $canonical) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    <a class="btn btn-sm btn-outline-secondary" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($canonical) ?>" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
    <a class="btn btn-sm btn-outline-secondary" href="https://t.me/share/url?url=<?= rawurlencode($canonical) ?>&text=<?= rawurlencode($l['album_name']) ?>" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> Telegram</a>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e($canonical) ?>"><i class="bi bi-clipboard"></i> Copiar link</button>
  </div>
</div>
<?php if (!$photos): ?><div class="card publico-card"><div class="card-body text-center text-muted py-5">Este álbum ainda não tem fotos.</div></div><?php else: ?>
<div class="photo-grid" id="publicGallery">
  <?php foreach ($photos as $p): $pid = (int) $p['id']; ?>
    <a class="photo-card" href="<?= url('/s/' . $token . '/' . $pid . '/imagem') ?>" data-caption="<?= e($p['caption'] ?? '') ?>"><img src="<?= url('/s/' . $token . '/' . $pid . '/imagem', ['t' => 1]) ?>" alt="<?= e($p['caption'] ?: $l['album_name']) ?>" loading="lazy"></a>
  <?php endforeach; ?>
</div>
<div class="lightbox d-none" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto ampliada">
  <button type="button" class="lb-close" data-lb-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
  <button type="button" class="lb-nav lb-prev" data-lb-prev aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>
  <button type="button" class="lb-nav lb-next" data-lb-next aria-label="Próxima"><i class="bi bi-chevron-right"></i></button>
  <div class="lb-stage"><img id="lbImg" alt=""></div>
  <div class="lb-bar"><div class="lb-caption small" id="lbCaption"></div><div class="lb-actions"><a class="btn btn-sm btn-light" id="lbDownload" href="#" download><i class="bi bi-download"></i> Baixar</a></div></div>
</div>
<?php endif; ?>
<?php if ($l['expires_at']): ?><p class="text-center small text-light opacity-75 mt-3">Link válido até <?= e(format_date($l['expires_at'])) ?>.</p><?php endif; ?>
