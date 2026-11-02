<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h1 class="h4 mb-0">Usuários</h1><span class="small text-muted">Senha do primeiro acesso: <code><?= e($defaultPassword) ?></code> (troca obrigatória ao entrar)</span></div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="table-responsive"><table class="table table-hover table-sm mb-0 align-middle">
      <thead class="table-light"><tr><th>Pessoa</th><th>Perfil</th><th>Situação</th><th>Último acesso</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $uid = (int) $u['id']; $self = $uid === Auth::id(); ?>
        <tr class="<?= $u['status'] !== 'ativo' ? 'table-secondary' : '' ?>">
          <td><strong><?= e($u['name']) ?></strong><br><span class="small text-muted"><?= e($u['email']) ?> · <?= (int) $u['photos'] ?> foto(s)</span><?= $u['must_change_password'] ? ' <span class="badge text-bg-warning" title="Ainda não criou a senha pessoal">1º acesso pendente</span>' : '' ?></td>
          <td><?php if ($self): ?><?= e(Auth::roleLabel($u['role'])) ?><?php else: ?><form method="post" action="<?= url('/admin/usuarios/' . $uid . '/perfil') ?>"><?= Csrf::field() ?><select name="role" class="form-select form-select-sm w-auto" data-autosubmit aria-label="Perfil"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $u['role']) ?>><?= e($l) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
          <td><span class="badge <?= $u['status'] === 'ativo' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($u['status']) ?></span></td>
          <td class="small text-muted"><?= e(format_datetime($u['last_login_at'])) ?></td>
          <td class="text-end text-nowrap"><?php if (!$self): ?>
            <form method="post" action="<?= url('/admin/usuarios/' . $uid . '/senha') ?>" class="d-inline" data-confirm="Voltar a senha de <?= e($u['name']) ?> para a padrão (<?= e($defaultPassword) ?>)? A pessoa terá de criar uma senha nova ao entrar e os aparelhos confiáveis serão esquecidos." data-confirm-ok="Redefinir"><?= Csrf::field() ?><input type="hidden" name="confirm" value="1"><button class="btn btn-sm btn-outline-secondary" title="Redefinir senha"><i class="bi bi-key"></i></button></form>
            <form method="post" action="<?= url('/admin/usuarios/' . $uid . '/ativar') ?>" class="d-inline" data-confirm="<?= $u['status'] === 'ativo' ? 'Desativar' : 'Reativar' ?> <?= e($u['name']) ?>?"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary" title="<?= $u['status'] === 'ativo' ? 'Desativar' : 'Reativar' ?>"><i class="bi bi-<?= $u['status'] === 'ativo' ? 'pause' : 'play' ?>"></i></button></form>
            <form method="post" action="<?= url('/admin/usuarios/' . $uid . '/remover') ?>" class="d-inline" data-confirm="Remover o acesso de <?= e($u['name']) ?> ao sistema de fotos?" data-confirm-type="danger"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger" title="Remover acesso"><i class="bi bi-person-x"></i></button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <form method="post" action="<?= url('/admin/usuarios') ?>" class="card mb-3" novalidate><?= Csrf::field() ?>
      <div class="card-header fw-semibold"><i class="bi bi-person-plus"></i> Nova pessoa</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label" for="name">Nome</label><input type="text" class="form-control form-control-sm<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name')) ?>" required><?= field_error('name') ?></div>
        <div class="mb-2"><label class="form-label" for="email">E-mail</label><input type="email" class="form-control form-control-sm<?= invalid('email') ?>" id="email" name="email" value="<?= e(old('email')) ?>" required><?= field_error('email') ?></div>
        <div class="mb-2"><label class="form-label" for="role">Perfil</label><select class="form-select form-select-sm" id="role" name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, old('role', 'membro')) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="small text-muted">A pessoa entra com a senha padrão <code><?= e($defaultPassword) ?></code> e é obrigada a criar a própria senha antes de ver qualquer foto.</div>
      </div>
      <div class="card-footer"><button class="btn btn-primary btn-sm w-100">Cadastrar</button></div>
    </form>
    <?php if ($others): ?>
    <form method="post" action="<?= url('/admin/usuarios/conceder') ?>" class="card"><?= Csrf::field() ?>
      <div class="card-header fw-semibold"><i class="bi bi-people"></i> Liberar quem já tem cadastro</div>
      <div class="card-body small"><p class="text-muted">Pessoas da Central de Mídia (mesmo banco) ainda sem acesso às fotos. Elas entram com a senha que já usam.</p>
        <select name="user_id" class="form-select form-select-sm mb-2"><?php foreach ($others as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?> — <?= e($o['email']) ?></option><?php endforeach; ?></select>
        <select name="role" class="form-select form-select-sm"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, 'membro') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="card-footer"><button class="btn btn-outline-primary btn-sm w-100">Liberar acesso</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>
