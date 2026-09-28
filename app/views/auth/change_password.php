<div class="card<?= $forced ? '' : ' mx-auto narrow-card' ?>">
  <div class="card-body p-4">
    <h2 class="h5 mb-1"><?= $forced ? 'Defina sua nova senha' : 'Trocar senha' ?></h2>
    <?php if ($forced): ?>
      <p class="text-muted small">Você está usando uma senha temporária. Escolha uma senha definitiva para continuar.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('/trocar-senha') ?>" novalidate>
      <?= Csrf::field() ?>
      <?php if (!$forced): ?>
        <div class="mb-3">
          <label class="form-label required" for="current_password">Senha atual</label>
          <input type="password" class="form-control<?= invalid('current_password') ?>" id="current_password" name="current_password" autocomplete="current-password" required>
          <?= field_error('current_password') ?>
        </div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label required" for="new_password">Nova senha</label>
        <div class="input-group">
          <input type="password" class="form-control<?= invalid('new_password') ?>" id="new_password" name="new_password" autocomplete="new-password" minlength="10" required>
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
    <?php if ($forced): ?>
      <form method="post" action="<?= url('/sair') ?>" class="text-center mt-3"><?= Csrf::field() ?>
        <button class="btn btn-link btn-sm">Sair</button>
      </form>
    <?php endif; ?>
  </div>
</div>
