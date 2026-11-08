<h1 class="h4 mb-3">Meus dados</h1>
<div class="row g-3">
  <div class="col-lg-6">
    <form method="post" action="<?= url('/meus-dados') ?>" class="card mb-3" novalidate><?= Csrf::field() ?>
      <div class="card-header fw-semibold"><i class="bi bi-person-badge"></i> Dados e segurança</div>
      <div class="card-body row g-3">
        <div class="col-12"><label class="form-label required" for="name">Nome</label><input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name', $me['name'])) ?>" maxlength="150" required><?= field_error('name') ?></div>
        <div class="col-12"><label class="form-label required" for="email">E-mail</label><input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" value="<?= e(old('email', $me['email'])) ?>" required><?= field_error('email') ?><div class="form-text">Recebe o código de acesso e o link de recuperação de senha.</div></div>
        <div class="col-12">
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="two_factor" value="1" id="two_factor"<?= checked($twoFactor || $twoFactorForced) ?><?= $twoFactorForced || $twoFactorMode === 'desligado' ? ' disabled' : '' ?>><label class="form-check-label" for="two_factor">Pedir um código por e-mail ao entrar (verificação em duas etapas)</label></div>
          <div class="form-text"><?= $twoFactorForced ? 'Obrigatório para o seu perfil.' : ($twoFactorMode === 'desligado' ? 'Desativado pelo administrador.' : 'Recomendado. Você pode marcar um aparelho como confiável para não pedir sempre.') ?></div>
        </div>
        <div class="col-12 small text-muted">Perfil: <strong><?= e(Auth::roleLabel($me['role'])) ?></strong> · último acesso <?= e(format_datetime($me['last_login_at'])) ?> · <a href="<?= url('/trocar-senha') ?>">trocar senha</a></div>
      </div>
      <div class="card-footer"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Salvar</button></div>
    </form>
    <?php if ($devices): ?>
    <div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between"><span><i class="bi bi-phone"></i> Aparelhos confiáveis</span><form method="post" action="<?= url('/meus-dados/aparelhos/esquecer') ?>" data-confirm="Esquecer todos os aparelhos? O código será pedido no próximo acesso em cada um."><?= Csrf::field() ?><button class="btn btn-link btn-sm p-0">esquecer todos</button></form></div>
      <ul class="list-group list-group-flush small"><?php foreach ($devices as $d): ?><li class="list-group-item"><?= e(truncate((string) $d['label'], 70)) ?><br><span class="text-muted">IP <?= e($d['ip']) ?> · desde <?= e(format_date($d['created_at'])) ?> · até <?= e(format_date($d['expires_at'])) ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </div>
  <?php if ($canShare): ?>
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header fw-semibold"><i class="bi bi-instagram"></i> Redes sociais (sua conta)</div>
      <div class="card-body">
        <?php if (!$cryptoOk): ?><div class="alert alert-warning small">O administrador precisa definir <code>APP_KEY</code> no .env para que os tokens possam ser guardados cifrados.</div><?php endif; ?>
        <?php foreach (SocialAccount::NETWORKS as $k => $l): ?>
          <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
            <span><i class="bi bi-<?= $k ?> fs-5 me-1"></i> <strong><?= e($l) ?></strong><br><span class="small text-muted"><?php if (isset($accounts[$k])): ?>Conectado: <?= e($accounts[$k]['account_name'] ?? $accounts[$k]['account_id']) ?><?= $accounts[$k]['token_expires_at'] ? ' · token válido até ' . e(format_date($accounts[$k]['token_expires_at'])) : ' · token sem data de expiração' ?><?php else: ?>Não conectado<?php endif; ?></span></span>
            <?php if (isset($accounts[$k])): ?><form method="post" action="<?= url('/meus-dados/redes/remover') ?>" data-confirm="Desconectar <?= e($l) ?>?"><?= Csrf::field() ?><input type="hidden" name="network" value="<?= e($k) ?>"><button class="btn btn-sm btn-outline-danger">Desconectar</button></form><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($discovered): ?>
          <form method="post" action="<?= url('/meus-dados/redes/salvar') ?>" class="border border-primary rounded-3 p-3 mt-3"><?= Csrf::field() ?>
            <div class="fw-semibold mb-2">Encontramos estas Páginas na conta de <?= e($discovered['user']['name'] ?? '') ?>. Escolha qual usar:</div>
            <?php foreach ($discovered['pages'] as $i => $pg): ?>
              <div class="form-check"><input class="form-check-input" type="radio" name="page_id" value="<?= e($pg['page_id']) ?>" id="pg<?= $i ?>"<?= checked($i === 0) ?>><label class="form-check-label" for="pg<?= $i ?>"><?= e($pg['page_name']) ?><?= $pg['ig_username'] ? ' · Instagram @' . e($pg['ig_username']) : ' · <span class="text-muted">sem Instagram profissional ligado</span>' ?></label></div>
            <?php endforeach; ?>
            <div class="mt-2"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="use_instagram" value="1" id="useIg" checked><label class="form-check-label" for="useIg">Instagram</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="use_facebook" value="1" id="useFb" checked><label class="form-check-label" for="useFb">Página do Facebook</label></div></div>
            <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-link-45deg"></i> Conectar</button>
          </form>
        <?php else: ?>
          <details class="mt-3"><summary class="fw-semibold cursor-pointer"><?= $accounts ? 'Reconectar / trocar conta' : 'Conectar minha conta' ?></summary>
            <form method="post" action="<?= url('/meus-dados/redes/descobrir') ?>" class="mt-2" novalidate><?= Csrf::field() ?>
              <label class="form-label" for="token">Token de acesso da Meta</label>
              <textarea class="form-control form-control-sm font-monospace" id="token" name="token" rows="3" required placeholder="EAAB..."></textarea>
              <div class="row g-2 mt-1"><div class="col-6"><input type="text" class="form-control form-control-sm" name="app_id" placeholder="App ID (opcional)"></div><div class="col-6"><input type="password" class="form-control form-control-sm" name="app_secret" placeholder="App Secret (opcional)" autocomplete="off"></div></div>
              <div class="form-text">Se informar App ID e App Secret, o token é trocado por um de longa duração (≈ 60 dias). O token é guardado cifrado e só você o usa.</div>
              <button class="btn btn-outline-primary btn-sm mt-2"<?= $cryptoOk ? '' : ' disabled' ?>><i class="bi bi-search"></i> Verificar e listar contas</button>
            </form>
            <div class="small mt-3">
              <strong>Como obter o token (uma vez):</strong>
              <ol class="ps-3 mb-0">
                <li>A conta do Instagram precisa ser <em>profissional</em> (Comercial ou Criador) e estar ligada a uma <em>Página do Facebook</em> que você administra.</li>
                <li>Em <a href="https://developers.facebook.com/" target="_blank" rel="noopener">developers.facebook.com</a>, crie um app (tipo “Empresa”) e adicione os produtos <em>Instagram Graph API</em> e <em>Facebook Login</em>.</li>
                <li>Abra <em>Ferramentas → Explorador da Graph API</em>, selecione o app e gere um <em>Token de acesso do usuário</em> com as permissões: <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>, <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>business_management</code>.</li>
                <li>Cole o token acima. Para ele durar ~60 dias, informe também o App ID e o App Secret (em <em>Configurações do app → Básico</em>).</li>
              </ol>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
