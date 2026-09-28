<h1 class="h4 mb-3">Pedidos de troca</h1>
<?php if (!$swaps): ?><div class="card"><div class="card-body text-center text-muted py-4">Nenhum pedido aguardando aprovação.</div></div><?php endif; ?>
<?php foreach ($swaps as $s): $can = Auth::canScheduleFunction((int) $s['function_id']); ?>
  <div class="card mb-2"><div class="card-body d-flex flex-wrap align-items-center gap-2">
    <span class="flex-grow-1"><a href="<?= url('/eventos/' . (int) $s['event_id'] . '/escala') ?>" class="fw-semibold"><?= e($s['event_title']) ?></a> · <?= e(format_datetime($s['starts_at'])) ?><br>
      <strong><?= e($s['from_name']) ?></strong> → <strong><?= e($s['to_name']) ?></strong> em <?= e($s['function_name']) ?> (o colega já aceitou)<?= $s['reason'] ? '<br><span class="small text-muted">' . e($s['reason']) . '</span>' : '' ?></span>
    <?php if ($can): ?>
      <form method="post" action="<?= url('/trocas/' . (int) $s['id'] . '/aprovar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-success">Aprovar</button></form>
      <form method="post" action="<?= url('/trocas/' . (int) $s['id'] . '/rejeitar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary">Rejeitar</button></form>
    <?php else: ?><span class="small text-muted">outra coordenação</span><?php endif; ?>
  </div></div>
<?php endforeach; ?>
