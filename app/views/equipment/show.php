<?php $id = (int) $q['id']; $canManage = Auth::can('equipment.manage'); $me = (int) Auth::id(); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/patrimonio') ?>">Patrimônio</a></li>
  <li class="breadcrumb-item active"><?= e($q['code']) ?></li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><span class="font-monospace"><?= e($q['code']) ?></span> · <?= e($q['name']) ?> <span class="badge text-bg-<?= Equipment::STATUS_COLORS[$q['status']] ?>"><?= e(Equipment::STATUSES[$q['status']]) ?></span></h1>
    <div class="text-muted small"><?= e(Equipment::CATEGORIES[$q['category']] ?? $q['category']) ?><?= $q['brand'] || $q['model'] ? ' · ' . e(trim($q['brand'] . ' ' . $q['model'])) : '' ?><?= $q['location'] ? ' · <i class="bi bi-geo-alt"></i> ' . e($q['location']) : '' ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('/patrimonio/' . $id . '/etiqueta') ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-qr-code"></i> Etiqueta</a>
    <?php if ($canManage): ?><a href="<?= url('/patrimonio/' . $id . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
    <?php if (Auth::can('incidents.report')): ?><a href="<?= url('/ocorrencias/nova', ['equipamento' => $id]) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-exclamation-triangle"></i> Ocorrência</a><?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <?php if ($q['status'] === 'disponivel'): ?>
      <div class="card mb-3 border-success"><div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-right"></i> Registrar empréstimo</div>
        <form method="post" action="<?= url('/patrimonio/' . $id . '/emprestar') ?>" class="card-body row g-2 align-items-end"><?= Csrf::field() ?>
          <?php if ($canManage): ?>
            <div class="col-md-4"><label class="form-label small" for="user_id">Quem leva</label><select class="form-select form-select-sm" id="user_id" name="user_id"><?php foreach ($team as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], $me) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
          <?php else: ?><div class="col-md-4 small text-muted">Em seu nome (<?= e(Auth::user()['name']) ?>).</div><?php endif; ?>
          <div class="col-md-4"><label class="form-label small" for="purpose">Finalidade</label><input type="text" class="form-control form-control-sm" id="purpose" name="purpose" maxlength="200" placeholder="Ex.: culto de jovens no salão"></div>
          <div class="col-md-2"><label class="form-label small" for="due_on">Devolver até</label><input type="date" class="form-control form-control-sm" id="due_on" name="due_on" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
          <div class="col-md-2"><button class="btn btn-sm btn-success w-100">Emprestar</button></div>
        </form>
      </div>
    <?php elseif ($q['status'] === 'emprestado' && $openLoan): ?>
      <div class="card mb-3 border-warning"><div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-in-left"></i> Emprestado para <?= e($openLoan['user_name']) ?> desde <?= e(format_datetime($openLoan['loaned_at'])) ?><?= $openLoan['due_on'] ? ' · devolver até <strong class="' . ($openLoan['due_on'] < date('Y-m-d') ? 'text-danger' : '') . '">' . e(format_date($openLoan['due_on'])) . '</strong>' : '' ?></div>
        <?php if ((int) $openLoan['user_id'] === $me || $canManage): ?>
        <form method="post" action="<?= url('/patrimonio/' . $id . '/devolver') ?>" class="card-body row g-2 align-items-end"><?= Csrf::field() ?>
          <?php if ($openLoan['purpose']): ?><div class="col-12 small text-muted">Finalidade: <?= e($openLoan['purpose']) ?></div><?php endif; ?>
          <div class="col-md-3"><label class="form-label small" for="condition">Condição na devolução</label><select class="form-select form-select-sm" id="condition" name="condition"><option value="ok">Em ordem</option><option value="danificado">Danificado (vai para manutenção)</option></select></div>
          <div class="col-md-6"><label class="form-label small" for="notes">Observações</label><input type="text" class="form-control form-control-sm" id="notes" name="notes" maxlength="500"></div>
          <div class="col-md-3"><button class="btn btn-sm btn-warning w-100">Registrar devolução</button></div>
        </form>
        <?php else: ?><div class="card-body small text-muted">Só quem pegou o item ou um coordenador pode registrar a devolução.</div><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php $openMaint = array_values(array_filter($maintenance, static fn($m) => $m['closed_on'] === null)); ?>
    <?php if ($canManage && $q['status'] === 'manutencao' && $openMaint): $m = $openMaint[0]; ?>
      <div class="card mb-3 border-danger"><div class="card-header bg-white fw-semibold"><i class="bi bi-tools"></i> Em manutenção (<?= e($m['kind']) ?>) desde <?= e(format_date($m['opened_on'])) ?><?= $m['provider'] ? ' · ' . e($m['provider']) : '' ?></div>
        <form method="post" action="<?= url('/patrimonio/' . $id . '/manutencao/' . (int) $m['id'] . '/encerrar') ?>" class="card-body row g-2 align-items-end"><?= Csrf::field() ?>
          <div class="col-12 small"><?= nl2br(e($m['description'])) ?></div>
          <div class="col-md-5"><label class="form-label small" for="result">Resultado</label><input type="text" class="form-control form-control-sm" id="result" name="result" maxlength="500" placeholder="O que foi feito"></div>
          <div class="col-md-2"><label class="form-label small" for="cost">Custo (R$)</label><input type="text" class="form-control form-control-sm" id="cost" name="cost" inputmode="decimal" value="<?= $m['cost_cents'] !== null ? e(number_format($m['cost_cents'] / 100, 2, ',', '.')) : '' ?>"></div>
          <div class="col-md-3"><label class="form-label small" for="outcome">Desfecho</label><select class="form-select form-select-sm" id="outcome" name="outcome"><option value="disponivel">Voltou ao uso</option><option value="baixar">Sem conserto: baixar do inventário</option></select></div>
          <div class="col-md-2"><button class="btn btn-sm btn-danger w-100">Encerrar</button></div>
        </form>
      </div>
    <?php elseif ($canManage && $q['status'] === 'manutencao' && !$openMaint): ?>
      <div class="alert alert-warning small">Item marcado como em manutenção sem registro aberto. Abra uma manutenção abaixo ou altere a situação em <a href="<?= url('/patrimonio/' . $id . '/editar') ?>" class="alert-link">Editar</a>.</div>
    <?php endif; ?>

    <?php if ($canManage && in_array($q['status'], ['disponivel', 'manutencao'], true) && !$openMaint): ?>
      <details class="card mb-3"><summary class="card-header bg-white fw-semibold cursor-pointer"><i class="bi bi-wrench-adjustable"></i> Abrir manutenção</summary>
        <form method="post" action="<?= url('/patrimonio/' . $id . '/manutencao') ?>" class="card-body row g-2 align-items-end"><?= Csrf::field() ?>
          <div class="col-md-3"><label class="form-label small" for="kind">Tipo</label><select class="form-select form-select-sm" id="kind" name="kind"><option value="corretiva">Corretiva (conserto)</option><option value="preventiva">Preventiva</option></select></div>
          <div class="col-md-5"><label class="form-label required small" for="description">Problema / serviço</label><input type="text" class="form-control form-control-sm<?= invalid('description') ?>" id="description" name="description" maxlength="500" value="<?= e(old('description')) ?>" required><?= field_error('description') ?></div>
          <div class="col-md-2"><label class="form-label small" for="provider">Fornecedor</label><input type="text" class="form-control form-control-sm" id="provider" name="provider" maxlength="120" value="<?= e(old('provider')) ?>"></div>
          <div class="col-md-2"><label class="form-label small" for="mcost">Custo (R$)</label><input type="text" class="form-control form-control-sm" id="mcost" name="cost" inputmode="decimal" value="<?= e(old('cost')) ?>"></div>
          <div class="col-12"><button class="btn btn-sm btn-outline-danger">Abrir manutenção</button></div>
        </form>
      </details>
    <?php endif; ?>

    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Histórico de empréstimos</div>
      <?php if (!$loans): ?><div class="card-body text-muted small">Nenhum empréstimo registrado.</div><?php else: ?>
      <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>Quem</th><th>Saída</th><th>Prazo</th><th>Devolução</th><th>Obs.</th></tr></thead><tbody>
      <?php foreach ($loans as $l): ?>
        <tr><td><?= e($l['user_name']) ?><?= $l['purpose'] ? '<br><span class="text-muted">' . e($l['purpose']) . '</span>' : '' ?></td><td><?= e(format_datetime($l['loaned_at'])) ?></td><td><?= e(format_date($l['due_on'])) ?></td>
          <td><?= $l['returned_at'] ? e(format_datetime($l['returned_at'])) . ($l['returned_condition'] === 'danificado' ? ' <span class="badge text-bg-danger">danificado</span>' : '') : '<span class="badge text-bg-warning">em aberto</span>' ?></td><td><?= e($l['notes'] ?? '') ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>
    <?php if ($maintenance): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Manutenções</div>
      <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>Tipo</th><th>Abertura</th><th>Encerramento</th><th>Descrição / resultado</th><th class="text-end">Custo</th></tr></thead><tbody>
      <?php foreach ($maintenance as $m): ?>
        <tr><td><?= e(ucfirst($m['kind'])) ?></td><td><?= e(format_date($m['opened_on'])) ?></td><td><?= $m['closed_on'] ? e(format_date($m['closed_on'])) : '<span class="badge text-bg-danger">aberta</span>' ?></td>
          <td><?= e($m['description']) ?><?= $m['provider'] ? ' <span class="text-muted">(' . e($m['provider']) . ')</span>' : '' ?><?= $m['result'] ? '<br><i class="bi bi-check2"></i> ' . e($m['result']) : '' ?></td>
          <td class="text-end"><?= $m['cost_cents'] !== null ? 'R$ ' . number_format($m['cost_cents'] / 100, 2, ',', '.') : '—' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    </div>
    <?php endif; ?>
    <?php if ($incidents): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Ocorrências ligadas</div>
      <ul class="list-group list-group-flush small"><?php foreach ($incidents as $i): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url('/ocorrencias/' . (int) $i['id']) ?>"><?= e($i['title']) ?></a><span><span class="badge text-bg-<?= Incident::SEVERITY_COLORS[$i['severity']] ?>"><?= e(Incident::SEVERITIES[$i['severity']]) ?></span> <span class="text-muted"><?= e(format_date($i['created_at'])) ?></span></span></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3">
      <?php if ($q['photo_path']): ?><img src="<?= url('/patrimonio/' . $id . '/foto') ?>" class="card-img-top" alt="Foto do item"><?php endif; ?>
      <div class="card-body small">
        <dl class="row mb-0">
          <dt class="col-5">Nº de série</dt><dd class="col-7"><?= e($q['serial_number'] ?? '—') ?></dd>
          <dt class="col-5">Aquisição</dt><dd class="col-7"><?= e(format_date($q['acquired_on'])) ?></dd>
          <dt class="col-5">Valor</dt><dd class="col-7"><?= $q['value_cents'] !== null ? 'R$ ' . number_format($q['value_cents'] / 100, 2, ',', '.') : '—' ?></dd>
          <dt class="col-5">Cadastro</dt><dd class="col-7"><?= e(format_date($q['created_at'])) ?></dd>
        </dl>
        <?php if ($q['notes']): ?><hr><?= nl2br(e($q['notes'])) ?><?php endif; ?>
      </div>
    </div>
    <div class="card"><div class="card-body text-center">
      <div data-qr="<?= e($qrUrl) ?>" data-size="4" class="qr-box mx-auto mb-2"></div>
      <div class="small text-muted">Aponte a câmera do celular para abrir esta ficha.</div>
    </div></div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script src="<?= asset('js/labels.js') ?>"></script>
