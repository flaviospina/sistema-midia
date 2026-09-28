<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Capacitação da equipe</h1>
  <div class="d-flex gap-2"><a href="<?= url('/capacitacao') ?>" class="btn btn-sm btn-outline-secondary">Minha trilha</a><a href="<?= url('/capacitacao/trilhas') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i> Trilhas</a></div>
</div>
<?php if ($ready): ?>
  <div class="card mb-3 border-success"><div class="card-header bg-white fw-semibold"><i class="bi bi-mortarboard"></i> Trilha completa: promover a apto</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($ready as $p): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2"><span><a href="<?= url('/capacitacao/pessoa/' . (int) $p['user_id']) ?>"><?= e($p['name']) ?></a> · <?= e($p['function_name']) ?> <span class="small text-muted">(<?= (int) $p['validated'] ?>/<?= (int) $p['required'] ?> validados)</span></span>
          <form method="post" action="<?= url('/capacitacao/pessoa/' . (int) $p['user_id'] . '/promover/' . (int) $p['function_id']) ?>" data-confirm="Promover <?= e($p['name']) ?> a apto em <?= e($p['function_name']) ?>? A pessoa passa a ser sugerida na escala."><?= Csrf::field() ?><button class="btn btn-sm btn-success">Promover a apto</button></form></li>
      <?php endforeach; ?>
    </ul></div>
<?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table table-hover table-sm mb-0 small">
  <thead class="table-light"><tr><th>Pessoa</th><th>Função</th><th>Nível</th><th>Trilha obrigatória</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Ninguém com função cadastrada.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): $pct = $r['required'] ? (int) round($r['validated'] * 100 / $r['required']) : 0; ?>
    <tr>
      <td><a href="<?= url('/capacitacao/pessoa/' . (int) $r['user_id']) ?>"><?= e($r['name']) ?></a></td>
      <td><?= e($r['function_name']) ?></td>
      <td><span class="badge <?= $r['level'] === 'aprendiz' ? 'text-bg-warning' : ($r['level'] === 'referencia' ? 'text-bg-primary' : 'text-bg-success') ?>"><?= e(ucfirst($r['level'])) ?></span></td>
      <td class="minw-130"><?php if (!$r['required']): ?><span class="text-muted">sem trilha</span><?php else: ?><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1 usage-bar"><div class="progress-bar <?= $r['complete'] ? 'bg-success' : '' ?>" data-width="<?= $pct ?>"></div></div><span><?= (int) $r['validated'] ?>/<?= (int) $r['required'] ?><?= (int) $r['done'] > (int) $r['validated'] ? ' <span class="text-warning" title="Concluídos aguardando validação">(+' . ((int) $r['done'] - (int) $r['validated']) . ')</span>' : '' ?></span></div><?php endif; ?></td>
      <td class="text-end"><a href="<?= url('/capacitacao/pessoa/' . (int) $r['user_id']) ?>" class="btn btn-sm btn-outline-secondary py-0">validar</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
