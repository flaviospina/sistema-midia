<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Entrar</h2>
    <form method="post" action="<?= url('/login') ?>" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="voltar" value="<?= e($back) ?>">
      <div class="mb-3">
        <label class="form-label" for="email">E-mail</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Senha</label>
        <div class="input-group">
          <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
          <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button class="btn btn-primary w-100">Entrar</button>
    </form>
    <div class="d-flex justify-content-between mt-3 small">
      <a href="<?= url('/esqueci-senha') ?>">Esqueci minha senha</a>
      <a href="<?= url('/cadastro') ?>">Sou membro da igreja, quero me cadastrar</a>
    </div>
  </div>
</div>
