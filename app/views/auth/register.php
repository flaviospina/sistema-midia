<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Criar cadastro</h2>
    <p class="text-muted small">Para membros da igreja. O acesso é liberado após aprovação da equipe de mídia.</p>
    <form method="post" action="<?= url('/cadastro') ?>" novalidate>
      <?= Csrf::field() ?>
      <div class="honeypot" aria-hidden="true"><label>Site<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="mb-3">
        <label class="form-label required" for="name">Nome completo</label>
        <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name')) ?>" maxlength="150" required>
        <?= field_error('name') ?>
      </div>
      <div class="mb-3">
        <label class="form-label required" for="email">E-mail</label>
        <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required>
        <?= field_error('email') ?>
      </div>
      <div class="mb-3">
        <label class="form-label required" for="whatsapp">WhatsApp</label>
        <input type="tel" class="form-control<?= invalid('whatsapp') ?>" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp')) ?>" data-mask="phone" placeholder="(11) 98888-7777" required>
        <?= field_error('whatsapp') ?>
      </div>
      <div class="row">
        <div class="col-sm-6 mb-3">
          <label class="form-label required" for="password">Senha</label>
          <input type="password" class="form-control<?= invalid('password') ?>" id="password" name="password" autocomplete="new-password" minlength="10" required>
          <div class="form-text">Mínimo 10 caracteres, com letras e números.</div>
          <?= field_error('password') ?>
        </div>
        <div class="col-sm-6 mb-3">
          <label class="form-label required" for="password_confirm">Confirmar senha</label>
          <input type="password" class="form-control<?= invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
          <?= field_error('password_confirm') ?>
        </div>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input<?= invalid('accept') ?>" type="checkbox" id="accept" name="accept" value="1" required>
        <label class="form-check-label" for="accept">
          Li e aceito o <a href="<?= url('/termo-de-uso') ?>" target="_blank">termo de uso e privacidade</a> (versão <?= e(TERMS_VERSION) ?>).
        </label>
        <?= field_error('accept') ?>
      </div>
      <button class="btn btn-primary w-100">Enviar cadastro</button>
    </form>
    <div class="text-center mt-3 small"><a href="<?= url('/login') ?>">Já tenho acesso</a></div>
  </div>
</div>
