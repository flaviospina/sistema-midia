<h1 class="h4 mb-3">Meus dados</h1>
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" action="<?= url('/meus-dados') ?>" enctype="multipart/form-data" class="card mb-3" novalidate>
      <?= Csrf::field() ?>
      <div class="card-header bg-white fw-semibold">Dados pessoais</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div id="photoPreview"><?php partial('avatar', ['u' => $u, 'size' => 96]) ?></div>
          <div class="flex-grow-1">
            <label class="form-label small mb-1" for="photo">Foto de perfil (opcional)</label>
            <input type="file" class="form-control form-control-sm" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-mb="<?= PHOTO_MAX_MB ?>">
            <?php if ($u['photo_path']): ?>
              <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label small" for="remove_photo">Remover foto</label></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label required" for="name">Nome completo</label>
          <input type="text" class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name', $u['name'])) ?>" maxlength="150" required>
          <?= field_error('name') ?>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" class="form-control" id="email" value="<?= e($u['email']) ?>" disabled>
            <div class="form-text">Para alterar o e-mail, fale com a liderança da mídia.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="whatsapp">WhatsApp</label>
            <input type="tel" class="form-control<?= invalid('whatsapp') ?>" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp', $u['whatsapp'] ? format_phone($u['whatsapp']) : '')) ?>" data-mask="phone">
            <?= field_error('whatsapp') ?>
            <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" id="notify_whatsapp" name="notify_whatsapp" value="1"<?= checked($notifyWa) ?>><label class="form-check-label small" for="notify_whatsapp">Receber avisos por WhatsApp (escala, lembretes, artes)</label></div>
          </div>
        </div>
        <dl class="row small mb-0 text-muted">
          <dt class="col-sm-4">Perfil</dt><dd class="col-sm-8"><?= e(Auth::roleLabel($u['role'])) ?></dd>
          <?php if ($functions): ?>
            <dt class="col-sm-4">Funções</dt><dd class="col-sm-8"><?php foreach ($functions as $f): ?><span class="badge badge-level-<?= e($f['level']) ?> me-1"><?= e($f['name']) ?> · <?= e(MediaFunction::LEVELS[$f['level']]) ?></span><?php endforeach; ?></dd>
          <?php endif; ?>
          <?php if ($ministries): ?>
            <dt class="col-sm-4">Ministérios</dt><dd class="col-sm-8"><?= e(implode(', ', array_map(static fn($m) => $m['name'] . ($m['is_leader'] ? ' (líder)' : ''), $ministries))) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button></div>
    </form>

    <form method="post" action="<?= url('/meus-dados/senha') ?>" class="card mb-3" novalidate>
      <?= Csrf::field() ?>
      <div class="card-header bg-white fw-semibold">Trocar senha</div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label required" for="current_password">Senha atual</label>
          <input type="password" class="form-control<?= invalid('current_password') ?>" id="current_password" name="current_password" autocomplete="current-password" required>
          <?= field_error('current_password') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label required" for="new_password">Nova senha</label>
          <input type="password" class="form-control<?= invalid('new_password') ?>" id="new_password" name="new_password" autocomplete="new-password" minlength="10" required>
          <?= field_error('new_password') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label required" for="password_confirm">Confirmar</label>
          <input type="password" class="form-control<?= invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
          <?= field_error('password_confirm') ?>
        </div>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-outline-primary"><i class="bi bi-key"></i> Alterar senha</button></div>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-check"></i> Privacidade (LGPD)</div>
      <div class="card-body small">
        <p>Você tem direito de acessar, corrigir, levar (portabilidade) e excluir seus dados, e de revogar consentimentos. Leia o <a href="<?= url('/termo-de-uso') ?>" target="_blank">termo de uso e privacidade</a>.</p>
        <a href="<?= url('/meus-dados/exportar') ?>" class="btn btn-sm btn-outline-primary mb-2"><i class="bi bi-download"></i> Baixar cópia dos meus dados (JSON)</a>

        <form method="post" action="<?= url('/meus-dados/solicitacao') ?>" class="border-top pt-3 mt-2">
          <?= Csrf::field() ?>
          <label class="form-label" for="request_type">Abrir solicitação</label>
          <select class="form-select form-select-sm mb-2" id="request_type" name="request_type" required>
            <?php foreach (DataRequest::TYPES as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <textarea class="form-control form-control-sm mb-2" name="details" rows="2" maxlength="2000" placeholder="Detalhes (opcional)"></textarea>
          <button class="btn btn-sm btn-outline-secondary">Enviar solicitação</button>
          <div class="form-text">Pedidos de exclusão são analisados pela liderança da mídia e concluídos em até 15 dias.</div>
        </form>

        <form method="post" action="<?= url('/meus-dados/revogar') ?>" class="border-top pt-3 mt-3" data-confirm="Revogar o consentimento de uso de imagem?">
          <?= Csrf::field() ?>
          <button class="btn btn-sm btn-outline-danger">Revogar consentimento de uso de imagem</button>
        </form>
      </div>
    </div>

    <?php if ($requests): ?>
    <div class="card mb-3">
      <div class="card-header bg-white fw-semibold">Minhas solicitações</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($requests as $r): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between"><span><?= e(DataRequest::TYPES[$r['request_type']] ?? $r['request_type']) ?></span><span class="badge <?= $r['status'] === 'aberta' ? 'text-bg-warning' : ($r['status'] === 'concluida' ? 'text-bg-success' : 'text-bg-secondary') ?>"><?= e(DataRequest::STATUSES[$r['status']]) ?></span></div>
            <div class="text-muted"><?= e(format_datetime($r['created_at'])) ?><?= $r['resolution_notes'] ? ' · ' . e($r['resolution_notes']) : '' ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header bg-white fw-semibold">Consentimentos registrados</div>
      <ul class="list-group list-group-flush small">
        <?php if (!$consents): ?><li class="list-group-item text-muted">Nenhum registro.</li><?php endif; ?>
        <?php foreach ($consents as $c): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span><?= e(Consent::TYPES[$c['consent_type']] ?? $c['consent_type']) ?> <span class="text-muted">v<?= e($c['terms_version']) ?></span></span>
            <span class="text-muted"><?= $c['revoked_at'] ? 'revogado em ' . e(format_datetime($c['revoked_at'])) : e(format_datetime($c['accepted_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
