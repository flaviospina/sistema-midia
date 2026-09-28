<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Esqueci minha senha</h2>
    <p>Nesta fase o sistema ainda não envia e-mails. Peça à liderança da mídia para gerar uma senha temporária para você.</p>
    <?php if (DPO_CONTACT): ?>
      <p class="mb-3">Contato: <a href="mailto:<?= e(DPO_CONTACT) ?>"><?= e(DPO_CONTACT) ?></a></p>
    <?php endif; ?>
    <a href="<?= url('/login') ?>" class="btn btn-outline-primary w-100">Voltar</a>
  </div>
</div>
