<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Olá, <?= e(explode(' ', $guest['guest_name'])[0]) ?>!</h2>
    <p class="text-muted small mb-3">Escolha as fotos e vídeos. No celular, você pode pegar da galeria ou usar a câmera. Se a conexão cair, é só abrir esta página de novo e reenviar o mesmo arquivo: o envio continua de onde parou.</p>
    <?php partial('uploader', ['maxBytes' => $maxBytes, 'metaFormId' => null]) ?>
    <?php if ($sent): ?>
      <div class="small text-muted mt-3">Já recebidos neste envio: <?= count($sent) ?> arquivo(s) (<?= e(format_bytes(array_sum(array_column($sent, 'size_bytes')))) ?>).</div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <a href="<?= url('/enviar') ?>" class="btn btn-link btn-sm">Alterar meus dados</a>
      <a href="<?= url('/enviar/concluido') ?>" id="finishBtn" class="btn btn-success">Concluir envio</a>
    </div>
    <div class="small text-muted mt-2">Limites por conexão: <?= GUEST_MAX_FILES_PER_HOUR ?> arquivos/hora e <?= GUEST_MAX_MB_PER_DAY ?> MB/dia.</div>
  </div>
</div>
