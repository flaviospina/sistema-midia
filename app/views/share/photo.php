<?php $pid = (int) $p['id']; ?>
<div class="row g-3 justify-content-center">
  <div class="col-lg-9">
    <div class="card publico-card">
      <img src="<?= url('/s/' . $token . '/imagem') ?>" class="card-img-top publico-img" alt="<?= e($p['caption'] ?: $p['album_name']) ?>">
      <div class="card-body">
        <h1 class="h4 mb-1"><?= e($p['caption'] ?: $p['album_name']) ?></h1>
        <div class="text-muted small"><i class="bi bi-calendar-event"></i> <?= e($p['album_name']) ?> · <?= e(weekday_pt($p['event_date'])) ?>, <?= e(format_date($p['event_date'])) ?></div>
      </div>
      <div class="card-footer d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-primary d-none" data-native-share data-title="<?= e($p['caption'] ?: $p['album_name']) ?>" data-url="<?= e($canonical) ?>"><i class="bi bi-share"></i> Compartilhar</button>
        <a class="btn btn-sm btn-outline-secondary" href="https://wa.me/?text=<?= rawurlencode(($p['caption'] ?: $p['album_name']) . ' ' . $canonical) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <a class="btn btn-sm btn-outline-secondary" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($canonical) ?>" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
        <a class="btn btn-sm btn-outline-secondary" href="https://t.me/share/url?url=<?= rawurlencode($canonical) ?>&text=<?= rawurlencode($p['caption'] ?: $p['album_name']) ?>" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> Telegram</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e($canonical) ?>"><i class="bi bi-clipboard"></i> Copiar link</button>
        <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url('/s/' . $token . '/imagem') ?>" download="<?= e(pathinfo($p['file_name'], PATHINFO_FILENAME)) ?>.jpg"><i class="bi bi-download"></i> Baixar imagem</a>
      </div>
    </div>
    <?php if ($l['expires_at']): ?><p class="text-center small text-light opacity-75 mt-2">Link válido até <?= e(format_date($l['expires_at'])) ?>.</p><?php endif; ?>
  </div>
</div>
