<?php $eid = (int) $e['id']; $val = static fn(string $k, $d = '') => old($k, $r[$k] ?? $d); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small">
  <li class="breadcrumb-item"><a href="<?= url('/eventos') ?>">Eventos</a></li>
  <li class="breadcrumb-item"><a href="<?= url('/eventos/' . $eid) ?>"><?= e($e['title']) ?></a></li>
  <li class="breadcrumb-item active">Relatório pós-culto</li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Relatório pós-culto</h1><div class="text-muted small"><?= e($e['title']) ?> · <?= e(format_datetime($e['starts_at'])) ?><?= $r ? ' · preenchido por ' . e($r['filled_by_name'] ?? '') . ' em ' . e(format_datetime($r['updated_at'] ?? $r['created_at'])) : '' ?></div></div>
  <a href="<?= url('/ocorrencias/nova', ['evento' => $eid]) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-exclamation-triangle"></i> Registrar ocorrência</a>
</div>
<form method="post" action="<?= url('/eventos/' . $eid . '/relatorio') ?>" class="row g-3" novalidate><?= Csrf::field() ?>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-broadcast"></i> Transmissão e público</div><div class="card-body row g-3">
      <div class="col-md-4"><label class="form-label" for="live_platform">Plataforma</label><input type="text" class="form-control" id="live_platform" name="live_platform" value="<?= e($val('live_platform', 'YouTube')) ?>" maxlength="60" list="platforms"><datalist id="platforms"><option value="YouTube"><option value="Instagram"><option value="Facebook"><option value="Não houve transmissão"></datalist></div>
      <div class="col-6 col-md-2"><label class="form-label" for="live_peak">Pico ao vivo</label><input type="number" class="form-control<?= invalid('live_peak') ?>" id="live_peak" name="live_peak" value="<?= e($val('live_peak')) ?>" min="0" max="9999999"><?= field_error('live_peak') ?></div>
      <div class="col-6 col-md-2"><label class="form-label" for="live_average">Média</label><input type="number" class="form-control<?= invalid('live_average') ?>" id="live_average" name="live_average" value="<?= e($val('live_average')) ?>" min="0"><?= field_error('live_average') ?></div>
      <div class="col-6 col-md-2"><label class="form-label" for="live_total_views">Views totais</label><input type="number" class="form-control<?= invalid('live_total_views') ?>" id="live_total_views" name="live_total_views" value="<?= e($val('live_total_views')) ?>" min="0"><?= field_error('live_total_views') ?></div>
      <div class="col-6 col-md-2"><label class="form-label" for="attendance_estimate">Presencial (est.)</label><input type="number" class="form-control<?= invalid('attendance_estimate') ?>" id="attendance_estimate" name="attendance_estimate" value="<?= e($val('attendance_estimate')) ?>" min="0"><?= field_error('attendance_estimate') ?></div>
    </div></div>
    <div class="card mb-3"><div class="card-body row g-3">
      <div class="col-12"><label class="form-label" for="summary">Como foi o culto (resumo da mídia)</label><textarea class="form-control" id="summary" name="summary" rows="3" maxlength="5000"><?= e($val('summary')) ?></textarea></div>
      <div class="col-md-6"><label class="form-label" for="highlights"><i class="bi bi-hand-thumbs-up"></i> O que funcionou bem</label><textarea class="form-control" id="highlights" name="highlights" rows="4" maxlength="5000"><?= e($val('highlights')) ?></textarea></div>
      <div class="col-md-6"><label class="form-label" for="improvements"><i class="bi bi-arrow-up-circle"></i> O que melhorar</label><textarea class="form-control" id="improvements" name="improvements" rows="4" maxlength="5000"><?= e($val('improvements')) ?></textarea></div>
    </div></div>
    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar relatório</button>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Checklist pré-culto</div>
      <?php if (!$progress): ?><div class="card-body small text-muted">Nenhum item marcado.</div><?php else: ?>
      <ul class="list-group list-group-flush small"><?php foreach ($functions as $f): $p = $progress[(int) $f['id']] ?? null; if (!$p) continue; ?><li class="list-group-item d-flex justify-content-between"><span><?= e($f['name']) ?></span><span class="badge <?= $p['done'] >= $p['total'] ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $p['done'] ?>/<?= $p['total'] ?></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <div class="card-footer bg-white"><a href="<?= url('/eventos/' . $eid . '/checklist') ?>" class="small">abrir checklist</a></div>
    </div>
    <div class="card"><div class="card-header bg-white fw-semibold">Ocorrências deste evento</div>
      <?php if (!$incidents): ?><div class="card-body small text-muted">Nenhuma ocorrência registrada.</div><?php else: ?>
      <ul class="list-group list-group-flush small"><?php foreach ($incidents as $i): ?><li class="list-group-item d-flex justify-content-between gap-2"><a href="<?= url('/ocorrencias/' . (int) $i['id']) ?>"><?= e($i['title']) ?></a><span class="badge text-bg-<?= Incident::SEVERITY_COLORS[$i['severity']] ?>"><?= e(Incident::SEVERITIES[$i['severity']]) ?></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
</form>
