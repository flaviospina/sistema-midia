<?php $eid = (int) $e['id']; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/eventos', ['mes' => substr($e['starts_at'], 0, 7)]) ?>">Eventos</a></li>
  <li class="breadcrumb-item"><a href="<?= url('/eventos/' . $eid) ?>"><?= e($e['title']) ?></a></li>
  <li class="breadcrumb-item active">Escala</li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Escala: <?= e($e['title']) ?></h1><div class="text-muted small"><?= e(Event::WEEKDAYS[(int) date('w', strtotime($e['starts_at']))]) ?>, <?= e(format_datetime($e['starts_at'])) ?><?= $e['location'] ? ' · ' . e($e['location']) : '' ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($slots): ?>
      <a href="<?= url('/eventos/' . $eid . '/escala', ['sugerir' => 1]) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-magic"></i> Ver sugestão</a>
      <form method="post" action="<?= url('/eventos/' . $eid . '/escala/sugerir') ?>" data-confirm="Escalar automaticamente as vagas abertas com rodízio justo?"><?= Csrf::field() ?><button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> Preencher automaticamente</button></form>
    <?php endif; ?>
    <a href="<?= url('/eventos/' . $eid) ?>" class="btn btn-sm btn-outline-secondary">Voltar</a>
  </div>
</div>

<?php foreach ($warnings as $w): ?><div class="alert alert-<?= e($w['type']) ?> py-2 small mb-2"><i class="bi bi-exclamation-triangle"></i> <?= e($w['text']) ?></div><?php endforeach; ?>

<?php if ($suggestion !== null): ?>
  <div class="card border-primary mb-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-magic"></i> Sugestão automática (rodízio justo)</div>
    <div class="card-body small">
      <?php if (!array_filter($suggestion)): ?><span class="text-muted">Nenhuma vaga aberta ou ninguém disponível.</span><?php endif; ?>
      <?php foreach ($suggestion as $fid => $people): if (!$people) continue; ?>
        <div><strong><?= e(MediaFunction::find($fid)['name'] ?? '') ?>:</strong>
          <?php foreach ($people as $p): ?><span class="badge badge-level-<?= e($p['level']) ?> me-1"><?= e($p['name']) ?> <span class="text-muted">(<?= (int) $p['recent'] ?> em <?= SCHEDULE_ROTATION_DAYS ?>d<?= $p['last'] ? ', última ' . e(format_date($p['last'])) : ', nunca' ?>)</span></span><?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="text-muted mt-2">Critério: quem está apto/referência, disponível e sem conflito; menos escalas nos últimos <?= SCHEDULE_ROTATION_DAYS ?> dias primeiro, depois quem serviu há mais tempo; garante um "referência" quando há 2+ vagas. Clique em "Preencher automaticamente" para aplicar.</div>
    </div></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <?php if (!$slots): ?>
      <div class="card"><div class="card-body text-muted">Defina as vagas ao lado (ou aplique um modelo) para começar a escalar.</div></div>
    <?php endif; ?>
    <?php foreach ($slots as $s): $fid = (int) $s['function_id']; $list = $assignments[$fid] ?? []; $active = array_filter($list, static fn($a) => $a['status'] !== 'recusado'); $open = (int) $s['quantity'] - count($active); $can = Auth::canScheduleFunction($fid); ?>
      <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <span class="fw-semibold"><?= e($s['function_name']) ?> <span class="text-muted small">· <?= count($active) ?>/<?= (int) $s['quantity'] ?></span></span>
          <?php if ($open > 0): ?><span class="badge text-bg-warning"><?= $open ?> vaga(s) aberta(s)</span><?php else: ?><span class="badge text-bg-success">completa</span><?php endif; ?>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($list as $a): ?>
            <li class="list-group-item d-flex align-items-center gap-2 flex-wrap">
              <?php partial('avatar', ['u' => ['id' => $a['user_id'], 'name' => $a['user_name'], 'photo_path' => $a['photo_path']], 'size' => 28]) ?>
              <span class="flex-grow-1"><?= e($a['user_name']) ?> <span class="badge badge-level-<?= e($a['level'] ?? 'aprendiz') ?>"><?= e(MediaFunction::LEVELS[$a['level'] ?? 'aprendiz'] ?? '') ?></span>
                <span class="badge <?= $a['status'] === 'confirmado' ? 'text-bg-success' : ($a['status'] === 'recusado' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= e(Assignment::STATUSES[$a['status']]) ?></span>
                <?php if ($a['note']): ?><span class="small text-muted">— <?= e($a['note']) ?></span><?php endif; ?></span>
              <?php if ($a['user_whatsapp']): ?><a href="<?= e(whatsapp_link($a['user_whatsapp'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
              <?php if ($can): ?><form method="post" action="<?= url('/escalas/' . (int) $a['id'] . '/remover') ?>" data-confirm="Remover <?= e($a['user_name']) ?> da escala?"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-x-lg"></i></button></form><?php endif; ?>
            </li>
          <?php endforeach; ?>
          <?php if ($can && isset($candidates[$fid])): ?>
            <li class="list-group-item bg-light">
              <form method="post" action="<?= url('/eventos/' . $eid . '/escala/escalar') ?>" class="d-flex gap-2 align-items-center flex-wrap">
                <?= Csrf::field() ?><input type="hidden" name="function_id" value="<?= $fid ?>">
                <select name="user_id" class="form-select form-select-sm w-auto flex-grow-1" required aria-label="Escalar pessoa">
                  <option value="">Escalar pessoa…</option>
                  <?php foreach ($candidates[$fid] as $c): if ($c['in_event']) continue; ?>
                    <option value="<?= (int) $c['id'] ?>"<?= $c['blocked'] ? ' class="text-muted"' : '' ?>><?= e($c['name']) ?> · <?= e(MediaFunction::LEVELS[$c['level']]) ?> · <?= (int) $c['recent'] ?> escala(s)/<?= SCHEDULE_ROTATION_DAYS ?>d<?= $c['unavailable'] ? ' · INDISPONÍVEL' : '' ?><?= $c['conflicts'] ? ' · CONFLITO' : '' ?><?= $c['overload'] ? ' · sobrecarga' : '' ?><?= $c['member_status'] !== 'ativo' ? ' · ' . e($c['member_status']) : '' ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Escalar</button>
              </form>
            </li>
          <?php elseif (!$can): ?>
            <li class="list-group-item bg-light small text-muted">Você não coordena esta função.</li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endforeach; ?>

    <?php if ($swaps): ?>
      <div class="card mb-3 border-warning"><div class="card-header bg-white fw-semibold">Pedidos de troca aguardando você</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($swaps as $s): ?>
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
              <span class="flex-grow-1"><strong><?= e($s['from_name']) ?></strong> → <strong><?= e($s['to_name']) ?></strong> em <?= e($s['function_name']) ?><?= $s['reason'] ? ' <span class="text-muted small">— ' . e($s['reason']) . '</span>' : '' ?></span>
              <form method="post" action="<?= url('/trocas/' . (int) $s['id'] . '/aprovar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-success">Aprovar</button></form>
              <form method="post" action="<?= url('/trocas/' . (int) $s['id'] . '/rejeitar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary">Rejeitar</button></form>
            </li>
          <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <?php if (Auth::can('events.manage')): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Vagas por função</div>
      <form method="post" action="<?= url('/eventos/' . $eid . '/escala/vagas') ?>" class="card-body">
        <?= Csrf::field() ?>
        <?php $map = array_column($slots, 'quantity', 'function_id'); foreach ($functions as $f): ?>
          <div class="d-flex align-items-center justify-content-between mb-1"><label class="small" for="qty<?= (int) $f['id'] ?>"><?= e($f['name']) ?></label><input type="number" class="form-control form-control-sm w-auto w-70" id="qty<?= (int) $f['id'] ?>" name="qty[<?= (int) $f['id'] ?>]" value="<?= (int) ($map[$f['id']] ?? 0) ?>" min="0" max="20"></div>
        <?php endforeach; ?>
        <button class="btn btn-sm btn-outline-primary w-100 mt-2">Salvar vagas</button>
      </form>
      <form method="post" action="<?= url('/eventos/' . $eid . '/escala/modelo') ?>" class="card-footer bg-white d-flex gap-1">
        <?= Csrf::field() ?>
        <select name="template_id" class="form-select form-select-sm"><option value="">Aplicar modelo…</option><?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-outline-secondary">OK</button>
      </form>
    </div>
    <?php endif; ?>
    <div class="card"><div class="card-body small text-muted">
      <div class="mb-1"><span class="badge text-bg-warning">Aguardando</span> a pessoa ainda não confirmou em <em>Minha escala</em>.</div>
      <div class="mb-1"><span class="badge text-bg-success">Confirmado</span> presença confirmada.</div>
      <div><span class="badge text-bg-secondary">Recusado</span> escale outra pessoa; o motivo aparece ao lado do nome.</div>
    </div></div>
  </div>
</div>
