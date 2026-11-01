<div class="card<?= $forced ? '' : ' mx-auto narrow-card' ?>">
  <div class="card-body p-4">
    <h2 class="h5 mb-1"><?= $forced ? 'Crie sua senha pessoal' : 'Trocar senha' ?></h2>
    <?php if ($forced): ?>
      <div class="alert alert-warning py-2 small mb-3">
        <i class="bi bi-exclamation-triangle"></i> <strong>Primeiro acesso.</strong> Você entrou com a senha padrão da igreja, que é a mesma para todos.
        Por segurança, o sistema só libera as fotos depois que você criar uma senha <strong>pessoal e secreta</strong>. Não use a senha padrão, seu nome, o nome da igreja ou sequências fáceis.
      </div>
    <?php endif; ?>
    <form method="post" action="<?= url('/trocar-senha') ?>" novalidate>
      <?= Csrf::field() ?>
      <?php if (!$forced): ?>
        <div class="mb-3"><label class="form-label required" for="current_password">Senha atual</label><input type="password" class="form-control<?= invalid('current_password') ?>" id="current_password" name="current_password" autocomplete="current-password" required><?= field_error('current_password') ?></div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label required" for="new_password">Nova senha</label>
        <div class="input-group">
          <input type="password" class="form-control<?= invalid('new_password') ?>" id="new_password" name="new_password" autocomplete="new-password" minlength="10" required data-strength="strength">
          <button class="btn btn-outline-secondary" type="button" data-toggle-password="new_password" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
        </div>
        <div class="progress mt-2 usage-bar" aria-hidden="true"><div class="progress-bar" id="strength" data-width="0"></div></div>
        <div class="form-text">Mínimo 10 caracteres, com letras e números. Dica: uma frase curta com números, ex.: <em>Louvor7Domingo!</em></div>
        <?= field_error('new_password') ?>
      </div>
      <div class="mb-3"><label class="form-label required" for="password_confirm">Confirmar nova senha</label><input type="password" class="form-control<?= invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" autocomplete="new-password" required><?= field_error('password_confirm') ?></div>
      <button class="btn btn-primary w-100"><i class="bi bi-shield-check"></i> Salvar nova senha</button>
    </form>
    <?php if ($forced): ?><form method="post" action="<?= url('/sair') ?>" class="text-center mt-3"><?= Csrf::field() ?><button class="btn btn-link btn-sm">Sair</button></form><?php endif; ?>
  </div>
</div>
