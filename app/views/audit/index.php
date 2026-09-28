<h1 class="h4 mb-3">Auditoria</h1>
<form class="card mb-3" method="get" action="<?= url('/auditoria') ?>">
  <div class="card-body row g-2">
    <div class="col-6 col-md-2"><input type="text" class="form-control form-control-sm" name="usuario" value="<?= e($filters['user_id']) ?>" placeholder="ID do usuário" inputmode="numeric"></div>
    <div class="col-6 col-md-3"><input type="text" class="form-control form-control-sm" name="acao" value="<?= e($filters['action']) ?>" placeholder="Ação (ex.: login)"></div>
    <div class="col-6 col-md-2">
      <select class="form-select form-select-sm" name="entidade"><option value="">Entidade</option>
        <?php foreach ($entities as $en): ?><option value="<?= e($en) ?>"<?= selected($en, $filters['entity']) ?>><?= e($en) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="de" value="<?= e($filters['from']) ?>" aria-label="De"></div>
    <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="ate" value="<?= e($filters['to']) ?>" aria-label="Até"></div>
    <div class="col-6 col-md-1"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
  </div>
</form>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0 small">
      <thead class="table-light"><tr><th>#</th><th>Quando</th><th>Quem</th><th>Ação</th><th>Entidade</th><th>IP</th></tr></thead>
      <tbody>
      <?php if (!$result['itens']): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum registro.</td></tr><?php endif; ?>
      <?php foreach ($result['itens'] as $a): ?>
        <tr>
          <td><a href="<?= url('/auditoria/' . (int) $a['id']) ?>"><?= (int) $a['id'] ?></a></td>
          <td class="text-nowrap"><?= e(format_datetime($a['created_at'])) ?></td>
          <td><?= $a['user_id'] ? '<a href="' . url('/usuarios/' . (int) $a['user_id']) . '">' . e($a['user_name'] ?? '#' . $a['user_id']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
          <td><code><?= e($a['action']) ?></code></td>
          <td><?= e($a['entity']) ?><?= $a['entity_id'] !== null ? ' #' . e($a['entity_id']) : '' ?></td>
          <td class="text-muted"><?= e($a['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php partial('pagination', ['result' => $result]) ?>
