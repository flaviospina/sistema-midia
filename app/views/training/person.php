<?php $uid = (int) $u['id']; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/capacitacao/equipe') ?>">Capacitação da equipe</a></li>
  <li class="breadcrumb-item active"><?= e($u['name']) ?></li>
</ol></nav>
<div class="d-flex align-items-center gap-2 mb-3"><?php partial('avatar', ['u' => $u, 'size' => 40]) ?><div><h1 class="h4 mb-0"><?= e($u['name']) ?></h1><div class="text-muted small"><?= $functions ? e(implode(', ', array_map(static fn($f) => $f['name'] . ' (' . $f['level'] . ')', $functions))) : 'sem funções cadastradas' ?></div></div></div>
<div class="row g-3">
<?php foreach ($byFunction as $fid => $group): $mine = $functions[$fid] ?? null; $st = Training::status($uid, $fid); ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100<?= $mine ? ' border-primary' : '' ?>">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e($group['name']) ?><?= $mine ? ' <span class="badge text-bg-light border">' . e(ucfirst($mine['level'])) . '</span>' : '' ?></span>
        <?php if ($mine && $mine['level'] === 'aprendiz' && $st['complete']): ?>
          <form method="post" action="<?= url('/capacitacao/pessoa/' . $uid . '/promover/' . $fid) ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-success py-0">Promover a apto</button></form>
        <?php elseif ($mine): ?><span class="badge <?= $st['complete'] ? 'text-bg-success' : 'text-bg-light border' ?>"><?= $st['validated'] ?>/<?= $st['required'] ?></span><?php endif; ?>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($group['items'] as $t): $tid = (int) $t['id']; $p = $progress[$tid] ?? null; $on = $p && $p['validated_at']; ?>
          <li class="list-group-item small d-flex justify-content-between align-items-center gap-2">
            <div class="min-w-0">
              <strong><?= e($t['title']) ?></strong><?= $t['required'] ? '' : ' <span class="text-muted">(opcional)</span>' ?>
              <?php if ($p): ?><div class="text-muted">concluído em <?= e(format_date($p['completed_at'], 'd/m/Y')) ?><?= $p['notes'] ? ' · ' . e($p['notes']) : '' ?><?= $on ? ' · validado por ' . e($p['validator_name'] ?? '') : '' ?></div><?php else: ?><div class="text-muted">não iniciado</div><?php endif; ?>
            </div>
            <form method="post" action="<?= url('/capacitacao/pessoa/' . $uid . '/validar/' . $tid) ?>"><?= Csrf::field() ?><input type="hidden" name="validated" value="<?= $on ? '0' : '1' ?>"><button class="btn btn-sm <?= $on ? 'btn-outline-secondary' : 'btn-outline-success' ?> py-0"><?= $on ? 'desvalidar' : 'validar' ?></button></form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endforeach; ?>
</div>
