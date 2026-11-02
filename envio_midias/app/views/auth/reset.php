<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Olá, <?= e(explode(' ', $name)[0]) ?>! Crie sua nova senha</h2>
    <p class="text-muted small">Este link é de uso único. Depois de salvar, entre com a nova senha.</p>
    <form method="post" action="<?= url('/redefinir-senha/' . $token) ?>" novalidate>
      <?= Csrf::field() ?>
      <div class="mb-3">
        <label class="form-label required" for="new_password">Nova senha</label>
        <div class="input-group">
          <input type="password" class="form-control<?= invalid('new_password') ?>" id="new_password" name="new_password" autocomplete="new-password" minlength="10" required autofocus>
          <button class="btn btn-outline-secondary" type="button" data-toggle-password="new_password" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
        </div>
        <div class="form-text">Mínimo 10 caracteres, com letras e números.</div>
        <?= field_error('new_password') ?>
      </div>
      <div class="mb-3">
        <label class="form-label required" for="password_confirm">Confirmar nova senha</label>
        <input type="password" class="form-control<?= invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
        <?= field_error('password_confirm') ?>
      </div>
      <button class="btn btn-primary w-100">Salvar nova senha</button>
    </form>
  </div>
</div>
