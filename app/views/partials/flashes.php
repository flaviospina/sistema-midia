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
    <div class="small mt-1">Quem receber o link vê a pré-visualização com a imagem (WhatsApp, Facebook, Instagram, Telegram) e abre a página sem precisar de login.</div>
  </div>
<?php endif; ?>
