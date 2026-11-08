<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1"><i class="bi bi-envelope-check"></i> Código de verificação</h2>
    <p class="text-muted small">Enviamos um código de 6 dígitos para <strong><?= e($email) ?></strong>. Ele vale por <?= TWO_FACTOR_CODE_MINUTES ?> minutos.</p>
    <form method="post" action="<?= url('/login/codigo') ?>" novalidate>
      <?= Csrf::field() ?>
      <div class="mb-3">
        <label class="form-label" for="code">Código</label>
        <input type="text" class="form-control form-control-lg text-center codigo" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus>
      </div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="trust" value="1" id="trust"><label class="form-check-label small" for="trust">Confiar neste aparelho por <?= TRUSTED_DEVICE_DAYS ?> dias (não pedir o código de novo)</label></div>
      <button class="btn btn-primary w-100">Confirmar</button>
    </form>
    <form method="post" action="<?= url('/login/codigo/reenviar') ?>" class="text-center mt-3"><?= Csrf::field() ?><button class="btn btn-link btn-sm">Não recebi, enviar outro código</button></form>
  </div>
</div>
