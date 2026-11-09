<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($f['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
<?php endforeach; ?>
<?php if (!empty($_SESSION['_share_link'])): $sl = $_SESSION['_share_link']; unset($_SESSION['_share_link']); ?>
  <div class="alert alert-success" role="alert">
    <div class="fw-semibold mb-1"><i class="bi bi-link-45deg"></i> Link público criado para <?= e($sl['label']) ?></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <input type="text" class="form-control form-control-sm share-url flex-grow-1 w-auto" value="<?= e($sl['url']) ?>" readonly aria-label="Link público">
      <button class="btn btn-sm btn-primary" type="button" data-copy="<?= e($sl['url']) ?>"><i class="bi bi-clipboard"></i> Copiar</button>
      <a class="btn btn-sm btn-outline-secondary" href="https://wa.me/?text=<?= rawurlencode($sl['url']) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
      <a class="btn btn-sm btn-outline-secondary" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($sl['url']) ?>" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($sl['url']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Abrir</a>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
      <a href="<?= e($sl['url']) ?>/cartao.jpg" target="_blank" rel="noopener" title="Imagem que aparece na prévia ao colar o link"><img src="<?= e($sl['url']) ?>/cartao.jpg" alt="Prévia do compartilhamento" class="share-card-preview"></a>
      <div class="small">Quem receber o link vê esta pré-visualização (WhatsApp, Facebook, Instagram, Telegram) e abre a página sem precisar de login.<br>Se a rede mostrar uma prévia antiga, atualize em <a href="https://developers.facebook.com/tools/debug/?q=<?= rawurlencode($sl['url']) ?>" target="_blank" rel="noopener">Facebook Sharing Debugger</a>.</div>
    </div>
  </div>
<?php endif; ?>
