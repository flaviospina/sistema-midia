<h1 class="h4 mb-3">Indisponibilidades da equipe</h1>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
  <thead class="table-light"><tr><th>Pessoa</th><th>Quando</th><th>Motivo</th><th></th></tr></thead>
  <tbody>
  <?php if (!$items): ?><tr><td colspan="4" class="text-center text-muted py-4">Nenhuma indisponibilidade vigente.</td></tr><?php endif; ?>
  <?php foreach ($items as $u): ?>
    <tr>
      <td><a href="<?= url('/usuarios/' . (int) $u['user_id']) ?>"><?= e($u['user_name']) ?></a></td>
      <td><i class="bi <?= $u['kind'] === 'recorrente' ? 'bi-arrow-repeat' : 'bi-calendar-x' ?>"></i> <?= e(Unavailability::describe($u)) ?></td>
      <td class="text-muted"><?= e($u['reason'] ?? '') ?></td>
      <td class="text-end"><form method="post" action="<?= url('/indisponibilidades/' . (int) $u['id'] . '/excluir') ?>" data-confirm="Remover esta indisponibilidade?"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
