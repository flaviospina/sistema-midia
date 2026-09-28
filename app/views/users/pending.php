<h1 class="h4 mb-3">Cadastros pendentes</h1>
<?php if (!$users): ?>
  <div class="card"><div class="card-body text-center text-muted py-4">Nenhum cadastro aguardando aprovação.</div></div>
<?php endif; ?>
<?php foreach ($users as $u): ?>
  <div class="card mb-2">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="flex-grow-1">
        <div class="fw-semibold"><?= e($u['name']) ?></div>
        <div class="small text-muted"><?= e($u['email']) ?> · <?= e(format_phone($u['whatsapp'])) ?> · enviado em <?= e(format_datetime($u['created_at'])) ?></div>
      </div>
      <form method="post" action="<?= url('/usuarios/' . (int) $u['id'] . '/aprovar') ?>" class="d-flex gap-2">
        <?= Csrf::field() ?>
        <?php if (Auth::is('admin')): ?>
          <select name="role" class="form-select form-select-sm" aria-label="Perfil">
            <?php foreach (Auth::ROLES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= selected($k, 'membro_igreja') ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="hidden" name="role" value="membro_igreja">
        <?php endif; ?>
        <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Aprovar</button>
      </form>
      <form method="post" action="<?= url('/usuarios/' . (int) $u['id'] . '/recusar') ?>" data-confirm="Recusar e apagar os dados de <?= e($u['name']) ?>?" data-confirm-type="danger">
        <?= Csrf::field() ?>
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Recusar</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
