<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Pessoas</h1>
  <div class="d-flex gap-2">
    <?php if ($pending): ?>
      <a href="<?= url('/usuarios/pendentes') ?>" class="btn btn-warning btn-sm"><i class="bi bi-person-check"></i> <?= $pending ?> pendente(s)</a>
    <?php endif; ?>
    <?php if (Auth::can('users.manage')): ?>
      <a href="<?= url('/usuarios/novo') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova pessoa</a>
    <?php endif; ?>
  </div>
</div>

<form class="card mb-3" method="get" action="<?= url('/usuarios') ?>">
  <div class="card-body row g-2">
    <div class="col-12 col-md-4">
      <input type="search" class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="Nome ou e-mail">
    </div>
    <div class="col-6 col-md-2">
      <select class="form-select form-select-sm" name="perfil">
        <option value="">Todos os perfis</option>
        <?php foreach (Auth::ROLES as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= selected($k, $filters['role']) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select class="form-select form-select-sm" name="funcao">
        <option value="">Qualquer função</option>
        <?php foreach ($functions as $f): ?>
          <option value="<?= (int) $f['id'] ?>"<?= selected($f['id'], $filters['function_id']) ?>><?= e($f['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select class="form-select form-select-sm" name="situacao">
        <option value="">Situação na equipe</option>
        <option value="ativo"<?= selected('ativo', $filters['member_status']) ?>>Ativo</option>
        <option value="afastado"<?= selected('afastado', $filters['member_status']) ?>>Afastado</option>
        <option value="em_treinamento"<?= selected('em_treinamento', $filters['member_status']) ?>>Em treinamento</option>
      </select>
    </div>
    <div class="col-6 col-md-2 d-flex gap-1">
      <select class="form-select form-select-sm" name="status">
        <option value="">Ativos e inativos</option>
        <option value="ativo"<?= selected('ativo', $filters['status']) ?>>Só ativos</option>
        <option value="inativo"<?= selected('inativo', $filters['status']) ?>>Só inativos</option>
        <option value="pendente"<?= selected('pendente', $filters['status']) ?>>Pendentes</option>
      </select>
      <button class="btn btn-sm btn-outline-primary" aria-label="Filtrar"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 table-responsive-stack">
      <thead class="table-light"><tr><th>Nome</th><th>Perfil</th><th>Funções</th><th>Contato</th><th>Situação</th></tr></thead>
      <tbody>
      <?php if (!$result['itens']): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma pessoa encontrada.</td></tr>
      <?php endif; ?>
      <?php foreach ($result['itens'] as $u): ?>
        <tr>
          <td data-label="Nome">
            <a href="<?= url('/usuarios/' . (int) $u['id']) ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none">
              <?php partial('avatar', ['u' => $u, 'size' => 40]) ?>
              <span><?= e($u['name']) ?></span>
            </a>
          </td>
          <td data-label="Perfil"><?= e(Auth::roleLabel($u['role'])) ?></td>
          <td data-label="Funções">
            <?php foreach ($u['functions'] as $f): ?>
              <span class="badge badge-level-<?= e($f['level']) ?>"><?= e($f['name']) ?></span>
            <?php endforeach; ?>
          </td>
          <td data-label="Contato" class="small">
            <?= e($u['email']) ?>
            <?php if ($u['whatsapp']): ?><br><a href="<?= e(whatsapp_link($u['whatsapp'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(format_phone($u['whatsapp'])) ?></a><?php endif; ?>
          </td>
          <td data-label="Situação">
            <?php if ($u['status'] === 'inativo'): ?><span class="badge text-bg-secondary">Inativo</span>
            <?php elseif ($u['status'] === 'pendente'): ?><span class="badge text-bg-warning">Pendente</span>
            <?php elseif ($u['member_status'] === 'afastado'): ?><span class="badge text-bg-danger">Afastado</span>
            <?php elseif ($u['member_status'] === 'em_treinamento'): ?><span class="badge text-bg-info">Em treinamento</span>
            <?php else: ?><span class="badge text-bg-success">Ativo</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php partial('pagination', ['result' => $result]) ?>
