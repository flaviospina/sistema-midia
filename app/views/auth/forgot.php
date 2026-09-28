<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Esqueci minha senha</h2>
    <p class="text-muted small">Informe o e-mail cadastrado. Enviaremos um link para você criar uma nova senha (válido por <?= PASSWORD_RESET_MINUTES ?> minutos).</p>
    <form method="post" action="<?= url('/esqueci-senha') ?>" novalidate>
      <?= Csrf::field() ?>
      <div class="mb-3">
        <label class="form-label" for="email">E-mail</label>
        <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus>
        <?= field_error('email') ?>
      </div>
      <button class="btn btn-primary w-100"><i class="bi bi-envelope"></i> Enviar link</button>
    </form>
    <div class="text-center mt-3 small"><a href="<?= url('/login') ?>">Voltar para o login</a></div>
    <?php if (DPO_CONTACT): ?><div class="text-center mt-2 small text-muted">Sem acesso ao e-mail? Fale com a liderança da mídia: <?= e(DPO_CONTACT) ?></div><?php endif; ?>
  </div>
</div>
