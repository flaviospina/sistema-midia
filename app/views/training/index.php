<?php $canManage = Auth::can('training.manage'); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Capacitação</h1><div class="text-muted small">Trilha de cada função. Marque o que concluiu; o coordenador valida e, com a trilha obrigatória completa, você passa a "apto".</div></div>
  <?php if ($canManage): ?><div class="d-flex gap-2"><a href="<?= url('/capacitacao/equipe') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people"></i> Equipe</a><a href="<?= url('/capacitacao/trilhas') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i> Trilhas</a></div><?php endif; ?>
</div>
<?php if ($ready): ?>
  <div class="alert alert-success py-2"><i class="bi bi-mortarboard"></i> Pronto(s) para promover a apto: <?php foreach ($ready as $p): ?><a href="<?= url('/capacitacao/equipe') ?>" class="alert-link"><?= e($p['name']) ?> (<?= e($p['function_name']) ?>)</a>; <?php endforeach; ?></div>
<?php endif; ?>
<?php $mineFirst = $byFunction; uksort($mineFirst, static fn($a, $b) => (isset($myFunctions[$b]) <=> isset($myFunctions[$a]))); ?>
<?php if (!$byFunction): ?><div class="alert alert-info">Nenhuma trilha cadastrada ainda.</div><?php endif; ?>
<div class="row g-3">
<?php foreach ($mineFirst as $fid => $group): $mine = $myFunctions[$fid] ?? null; $req = array_filter($group['items'], static fn($t) => $t['required']); $done = count(array_filter($req, static fn($t) => isset($progress[(int) $t['id']]['validated_at']) && $progress[(int) $t['id']]['validated_at'])); ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100<?= $mine ? ' border-primary' : '' ?>">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e($group['name']) ?><?= $mine ? ' <span class="badge text-bg-light border">' . e(ucfirst($mine['level'])) . '</span>' : '' ?></span>
        <?php if ($mine && $req): ?><span class="badge <?= $done >= count($req) ? 'text-bg-success' : 'text-bg-light border' ?>" title="Obrigatórios validados"><?= $done ?>/<?= count($req) ?></span><?php endif; ?>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($group['items'] as $t): $tid = (int) $t['id']; $p = $progress[$tid] ?? null; ?>
          <li class="list-group-item small">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="min-w-0">
                <?php if ($p && $p['validated_at']): ?><i class="bi bi-patch-check-fill text-success" title="Validado por <?= e($p['validator_name'] ?? '') ?>"></i><?php elseif ($p): ?><i class="bi bi-check-circle text-warning" title="Concluído, aguardando validação"></i><?php else: ?><i class="bi bi-circle text-muted"></i><?php endif; ?>
                <strong><?= e($t['title']) ?></strong><?= $t['required'] ? '' : ' <span class="text-muted">(opcional)</span>' ?>
                <?php if ($t['description']): ?><div class="text-muted"><?= nl2br(e($t['description'])) ?></div><?php endif; ?>
                <?php if ($t['resource_url']): ?><a href="<?= e($t['resource_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> material</a> <?php endif; ?>
                <?php if ($t['file_id']): ?><a href="<?= url('/arquivos/' . (int) $t['file_id']) ?>"><i class="bi bi-file-earmark"></i> <?= e($t['file_name'] ?? 'arquivo') ?></a><?php endif; ?>
              </div>
              <?php if ($mine): ?>
                <form method="post" action="<?= url('/capacitacao/' . $tid . '/concluir') ?>"><?= Csrf::field() ?>
                  <?php if ($p && $p['validated_at']): ?><span class="badge text-bg-success">validado</span>
                  <?php elseif ($p): ?><input type="hidden" name="undo" value="1"><button class="btn btn-xs btn-outline-secondary btn-sm">desfazer</button>
                  <?php else: ?><button class="btn btn-sm btn-outline-primary">Concluí</button><?php endif; ?>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endforeach; ?>
</div>
