<?php $isMedia = in_array($u['role'], Auth::MEDIA_ROLES, true); ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="d-flex align-items-center gap-3">
    <?php partial('avatar', ['u' => $u, 'size' => 64]) ?>
    <div>
      <h1 class="h4 mb-0"><?= e($u['name']) ?></h1>
      <div class="text-muted small"><?= e(Auth::roleLabel($u['role'])) ?>
        <?php if ($u['status'] === 'inativo'): ?> · <span class="badge text-bg-secondary">Inativo</span><?php endif; ?>
        <?php if ($u['status'] === 'pendente'): ?> · <span class="badge text-bg-warning">Pendente</span><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (Auth::can('users.manage')): ?>
      <a href="<?= url('/usuarios/' . (int) $u['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
    <?php endif; ?>
    <?php if ($isMedia && Auth::can('users.functions')): ?>
      <a href="<?= url('/usuarios/' . (int) $u['id'] . '/funcoes') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders"></i> Funções</a>
    <?php endif; ?>
    <a href="<?= url('/usuarios') ?>" class="btn btn-sm btn-outline-secondary">Voltar</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Dados</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-sm-4">E-mail</dt><dd class="col-sm-8"><?= e($u['email']) ?></dd>
          <dt class="col-sm-4">WhatsApp</dt><dd class="col-sm-8"><?php if ($u['whatsapp']): ?><a href="<?= e(whatsapp_link($u['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e(format_phone($u['whatsapp'])) ?></a><?php else: ?>—<?php endif; ?></dd>
          <?php if ($isMedia): ?>
            <dt class="col-sm-4">Situação na equipe</dt><dd class="col-sm-8"><?= e(['ativo' => 'Ativo', 'afastado' => 'Afastado', 'em_treinamento' => 'Em treinamento'][$u['member_status']] ?? '—') ?></dd>
            <dt class="col-sm-4">Entrada na equipe</dt><dd class="col-sm-8"><?= e(format_date($u['joined_at'])) ?></dd>
            <?php if ($u['notes']): ?><dt class="col-sm-4">Observações</dt><dd class="col-sm-8"><?= nl2br(e($u['notes'])) ?></dd><?php endif; ?>
          <?php endif; ?>
          <dt class="col-sm-4">Ministérios</dt>
          <dd class="col-sm-8"><?php if ($ministries): foreach ($ministries as $m): ?><span class="badge text-bg-light border me-1"><?= e($m['name']) ?><?= $m['is_leader'] ? ' (líder)' : '' ?></span><?php endforeach; else: ?>—<?php endif; ?></dd>
          <dt class="col-sm-4">Cadastrado em</dt><dd class="col-sm-8"><?= e(format_datetime($u['created_at'])) ?></dd>
          <dt class="col-sm-4">Último acesso</dt><dd class="col-sm-8"><?= e(format_datetime($u['last_login_at'])) ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <?php if ($isMedia): ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Funções</div>
      <?php if (!$functions): ?>
        <div class="card-body text-muted small">Nenhuma função atribuída.</div>
      <?php else: ?>
      <table class="table table-sm mb-0">
        <thead><tr><th>Função</th><th>Nível</th><th>Treinamento</th></tr></thead>
        <tbody>
        <?php foreach ($functions as $f): ?>
          <tr>
            <td><?= e($f['name']) ?><?= $f['is_coordinator'] ? ' <span class="badge text-bg-primary">coord.</span>' : '' ?></td>
            <td><span class="badge badge-level-<?= e($f['level']) ?>"><?= e(MediaFunction::LEVELS[$f['level']]) ?></span></td>
            <td class="small"><?= e(format_date($f['trained_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('privacy.manage') && $consents): ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Consentimentos (LGPD)</div>
      <table class="table table-sm mb-0 small">
        <thead><tr><th>Tipo</th><th>Versão</th><th>Aceite</th><th>IP</th><th>Revogado</th></tr></thead>
        <tbody>
        <?php foreach ($consents as $c): ?>
          <tr>
            <td><?= e(Consent::TYPES[$c['consent_type']] ?? $c['consent_type']) ?></td>
            <td><?= e($c['terms_version']) ?></td>
            <td><?= e(format_datetime($c['accepted_at'])) ?></td>
            <td><?= e($c['ip']) ?></td>
            <td><?= $c['revoked_at'] ? e(format_datetime($c['revoked_at'])) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('users.manage') && $u['status'] !== 'pendente' && (int) $u['id'] !== Auth::id()): ?>
  <div class="col-12">
    <div class="card border-danger-subtle">
      <div class="card-header bg-white fw-semibold">Acesso e conta</div>
      <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <?php if ($u['status'] === 'ativo'): ?>
          <form method="post" action="<?= url('/usuarios/' . (int) $u['id'] . '/redefinir-senha') ?>" data-confirm="Gerar uma senha temporária para <?= e($u['name']) ?>? A senha atual deixará de funcionar.">
            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-primary"><i class="bi bi-key"></i> Gerar senha temporária</button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= url('/usuarios/' . (int) $u['id'] . '/status') ?>" data-confirm="<?= $u['status'] === 'ativo' ? 'Desativar o acesso desta pessoa?' : 'Reativar o acesso desta pessoa?' ?>">
          <?= Csrf::field() ?>
          <button class="btn btn-sm <?= $u['status'] === 'ativo' ? 'btn-outline-warning' : 'btn-outline-success' ?>">
            <i class="bi <?= $u['status'] === 'ativo' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i> <?= $u['status'] === 'ativo' ? 'Desativar acesso' : 'Reativar acesso' ?>
          </button>
        </form>
        <?php if (Auth::can('privacy.manage')): ?>
          <button class="btn btn-sm btn-outline-danger ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#anonBox"><i class="bi bi-eraser"></i> Remover dados pessoais</button>
        <?php endif; ?>
      </div>
      <?php if (Auth::can('privacy.manage')): ?>
      <div class="collapse" id="anonBox">
        <div class="card-body border-top">
          <p class="small mb-2">Remove nome, e-mail, WhatsApp, foto, funções e ministérios de forma <strong>irreversível</strong>. O histórico de auditoria é mantido sem identificação. Use para atender pedido de exclusão (LGPD) ou desligamento definitivo.</p>
          <form method="post" action="<?= url('/usuarios/' . (int) $u['id'] . '/anonimizar') ?>" class="d-flex flex-wrap gap-2 align-items-center">
            <?= Csrf::field() ?>
            <input type="text" name="confirm" class="form-control form-control-sm w-auto" placeholder="Digite o e-mail para confirmar" autocomplete="off" required>
            <button class="btn btn-sm btn-danger">Confirmar remoção</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
