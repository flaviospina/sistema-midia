<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Cultos fixos</h1>
  <div class="d-flex gap-2">
    <form method="post" action="<?= url('/recorrencias/gerar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Gerar eventos agora</button></form>
    <a href="<?= url('/recorrencias/nova') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo culto fixo</a>
  </div>
</div>
<p class="small text-muted">Os eventos são gerados automaticamente para as próximas <?= $weeksAhead ?> semanas (pelo cron diário ou pelo botão acima). Cada evento gerado pode ser editado ou cancelado individualmente.</p>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 table-responsive-stack">
  <thead class="table-light"><tr><th>Nome</th><th>Quando</th><th>Modelo de escala</th><th>Vigência</th><th class="text-center">Futuros</th><th></th></tr></thead>
  <tbody>
  <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum culto fixo. Cadastre os cultos semanais para que a agenda se preencha sozinha.</td></tr><?php endif; ?>
  <?php foreach ($items as $r): ?>
    <tr class="<?= $r['active'] ? '' : 'text-muted' ?>">
      <td data-label="Nome"><span class="fw-semibold"><?= e($r['title']) ?></span> <span class="small text-muted"><?= e(Event::TYPES[$r['event_type']]) ?></span><?= !$r['active'] ? ' <span class="badge text-bg-secondary">inativo</span>' : '' ?></td>
      <td data-label="Quando"><?= e(Recurrence::describe($r)) ?> · <?= (int) $r['duration_minutes'] ?> min<?= $r['location'] ? ' · ' . e($r['location']) : '' ?></td>
      <td data-label="Modelo"><?= e($r['template_name'] ?? '—') ?></td>
      <td data-label="Vigência"><?= e(format_date($r['starts_on'])) ?><?= $r['ends_on'] ? ' a ' . e(format_date($r['ends_on'])) : ' em diante' ?></td>
      <td data-label="Futuros" class="text-center"><?= (int) $r['future_events'] ?></td>
      <td class="text-end"><a href="<?= url('/recorrencias/' . (int) $r['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
