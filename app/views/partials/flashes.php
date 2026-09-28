<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($f['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
<?php endforeach; ?>
<?php if (!empty($_SESSION['_temp_password'])): $tp = $_SESSION['_temp_password']; unset($_SESSION['_temp_password']); ?>
  <div class="alert alert-warning" role="alert">
    <strong>Senha temporária de <?= e($tp['name']) ?>:</strong>
    <code class="fs-5 user-select-all"><?= e($tp['password']) ?></code>
    <button class="btn btn-sm btn-outline-dark ms-2" type="button" data-copy="<?= e($tp['password']) ?>"><i class="bi bi-clipboard"></i> Copiar</button>
    <div class="small mt-1">Envie por um canal seguro para <?= e($tp['email']) ?>. Ela será exibida só desta vez; a pessoa deverá trocá-la no primeiro acesso.</div>
  </div>
<?php endif; ?>
