<?php $rid = (int) $r['id']; $late = $r['publish_on'] < date('Y-m-d') && !in_array($r['status'], ['aprovado', 'publicado', 'cancelado'], true); $current = $versions[0] ?? null; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= url('/artes') ?>">Pedidos de arte</a></li><li class="breadcrumb-item active">#<?= $rid ?></li></ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($r['title']) ?> <span class="badge text-bg-<?= ArtRequest::STATUS_COLORS[$r['status']] ?>"><?= e(ArtRequest::STATUSES[$r['status']]) ?></span><?= $r['is_urgent'] ? ' <span class="badge text-bg-danger">urgente</span>' : '' ?></h1>
    <div class="small text-muted">Pedido por <strong><?= e($r['requester_name']) ?></strong><?= $r['ministry_name'] ? ' · ' . e($r['ministry_name']) : '' ?> em <?= e(format_datetime($r['created_at'])) ?>
      · publicação em <strong class="<?= $late ? 'text-danger' : '' ?>"><?= e(format_date($r['publish_on'])) ?></strong>
      <?= $r['event_title'] ? ' · evento: <a href="' . url('/eventos/' . (int) $r['event_id']) . '">' . e($r['event_title']) . '</a>' : '' ?>
      <?= $r['designer_name'] ? ' · designer: <strong>' . e($r['designer_name']) . '</strong>' : '' ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canEdit): ?><a href="<?= url('/artes/' . $rid . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar briefing</a><?php endif; ?>
    <?php foreach ($actions as $act => $label): $needsNote = in_array($act, ['pedir_ajustes', 'cancelar'], true); $cls = match ($act) { 'pedir_ajustes' => 'btn-outline-danger', 'cancelar' => 'btn-outline-secondary', 'reabrir', 'retomar' => 'btn-outline-primary', default => 'btn-success' }; ?>
      <?php if ($needsNote): ?>
        <button class="btn btn-sm <?= $cls ?>" type="button" data-bs-toggle="collapse" data-bs-target="#act_<?= e($act) ?>"><?= e($label) ?></button>
      <?php else: ?>
        <form method="post" action="<?= url('/artes/' . $rid . '/acao') ?>" data-confirm="<?= e($label) ?>?"><?= Csrf::field() ?><input type="hidden" name="action" value="<?= e($act) ?>"><button class="btn btn-sm <?= $cls ?>"><?= e($label) ?></button></form>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php foreach (['pedir_ajustes' => 'Descreva os ajustes necessários', 'cancelar' => 'Motivo do cancelamento'] as $act => $ph): if (!isset($actions[$act])) continue; ?>
  <form method="post" action="<?= url('/artes/' . $rid . '/acao') ?>" class="collapse mb-3" id="act_<?= e($act) ?>"><?= Csrf::field() ?><input type="hidden" name="action" value="<?= e($act) ?>">
    <div class="input-group"><textarea name="notes" class="form-control" rows="2" placeholder="<?= e($ph) ?>" required></textarea><button class="btn <?= $act === 'cancelar' ? 'btn-secondary' : 'btn-danger' ?>"><?= e($actions[$act]) ?></button></div></form>
<?php endforeach; ?>
<?php if ($r['status'] === 'ajustes' && $r['ajustes_from']): ?><div class="alert alert-danger py-2 small"><i class="bi bi-arrow-counterclockwise"></i> Ajustes pedidos na etapa <strong><?= e(ArtRequest::STATUSES[$r['ajustes_from']] ?? $r['ajustes_from']) ?></strong>. Veja o último comentário e envie uma nova versão.</div><?php endif; ?>
<?php if ($r['status'] === 'cancelado'): ?><div class="alert alert-secondary py-2 small">Cancelado: <?= e($r['cancelled_reason']) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <?php if ($current): ?>
      <div class="card mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Versão <?= (int) $current['version_no'] ?> (atual)</span><span class="small text-muted"><?= e($current['creator_name'] ?? '') ?> · <?= e(format_datetime($current['created_at'])) ?></span></div>
        <div class="preview-box">
          <?php if ($current['thumb_ref'] && FileTypes::isImage($current['extension'])): ?><a href="<?= url('/arquivos/' . (int) $current['file_id']) ?>"><img src="<?= url('/arquivos/' . (int) $current['file_id'] . '/ver') ?>" alt=""></a>
          <?php elseif ($current['thumb_ref']): ?><a href="<?= url('/arquivos/' . (int) $current['file_id']) ?>"><img src="<?= url('/arquivos/' . (int) $current['file_id'] . '/miniatura') ?>" alt=""></a>
          <?php else: ?><a href="<?= url('/arquivos/' . (int) $current['file_id']) ?>" class="text-white-50 p-4 text-center"><i class="bi <?= FileTypes::icon($current['category'], $current['extension']) ?> fs-1 d-block"></i><?= e($current['original_name']) ?></a><?php endif; ?>
        </div>
        <div class="card-footer bg-white small d-flex justify-content-between flex-wrap gap-2"><span><?= e($current['original_name']) ?> · <?= e(format_bytes((int) $current['size_bytes'])) ?><?= $current['notes'] ? ' · ' . e($current['notes']) : '' ?></span><a href="<?= url('/arquivos/' . (int) $current['file_id'] . '/download') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Baixar</a></div>
      </div>
    <?php endif; ?>

    <?php if ($canVersion): ?>
      <div class="card mb-3 border-primary"><div class="card-header bg-white fw-semibold"><i class="bi bi-brush"></i> Enviar <?= $current ? 'nova versão' : 'primeira versão' ?></div>
        <div class="card-body">
          <div id="versionMeta" class="mb-2"><input type="hidden" name="art_request_id" value="<?= $rid ?>"><input type="hidden" name="art_kind" value="versao"><input type="hidden" name="folder_id" value=""><input type="hidden" name="category" value="arte_final">
            <input type="text" name="description" class="form-control form-control-sm" maxlength="500" placeholder="O que mudou nesta versão (opcional)"></div>
          <?php partial('uploader', ['maxBytes' => UPLOAD_MAX_BYTES, 'metaFormId' => 'versionMeta', 'reloadOnDone' => true]) ?>
        </div>
        <div class="card-footer bg-white small text-muted">Após enviar, use <strong>"Enviar para revisão do solicitante"</strong>. Cada versão vira um arquivo em <em><?= e(ART_FOLDER_NAME) ?></em>.</div>
      </div>
    <?php endif; ?>

    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Briefing</div>
      <div class="card-body">
        <div class="mb-2"><?php foreach (ArtRequest::formatsOf($r) as $f): ?><span class="badge text-bg-light border"><?= e(ArtRequest::FORMATS[$f] ?? $f) ?></span> <?php endforeach; ?><?= $r['needs_pastoral'] ? '<span class="badge text-bg-warning"><i class="bi bi-person-badge"></i> exige aprovação pastoral</span>' : '' ?></div>
        <p class="mb-2"><?= nl2br(e($r['briefing'])) ?></p>
        <?php if ($r['texts']): ?><div class="small text-muted fw-semibold">Textos que devem constar</div><pre class="json mb-0"><?= e($r['texts']) ?></pre><?php endif; ?>
      </div>
      <?php if ($attachments || $canAttach): ?>
      <div class="card-footer bg-white">
        <div class="small fw-semibold mb-1">Anexos e referências</div>
        <?php foreach ($attachments as $a): ?><a href="<?= url('/arquivos/' . (int) $a['id']) ?>" class="badge text-bg-light border text-decoration-none me-1"><i class="bi <?= FileTypes::icon($a['category'], $a['extension']) ?>"></i> <?= e(truncate($a['original_name'], 40)) ?></a><?php endforeach; ?>
        <?php if ($canAttach): ?>
          <div id="attachMeta" class="d-none"><input type="hidden" name="art_request_id" value="<?= $rid ?>"><input type="hidden" name="art_kind" value="anexo"><input type="hidden" name="folder_id" value=""></div>
          <div class="mt-2"><?php partial('uploader', ['maxBytes' => UPLOAD_MAX_BYTES, 'metaFormId' => 'attachMeta', 'reloadOnDone' => true]) ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card"><div class="card-header bg-white fw-semibold">Comentários e histórico</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($comments as $c): ?>
          <li class="list-group-item small <?= $c['kind'] === 'historico' ? 'text-muted' : '' ?>">
            <div class="d-flex justify-content-between"><span><?= $c['kind'] === 'historico' ? '<i class="bi bi-clock-history"></i> ' : '<strong>' . e($c['user_name'] ?? '—') . '</strong>' ?><?= $c['version_no'] ? ' <span class="badge text-bg-light border">v' . (int) $c['version_no'] . '</span>' : '' ?></span><span class="text-muted"><?= e(format_datetime($c['created_at'])) ?></span></div>
            <div><?= nl2br(e($c['body'])) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!in_array($r['status'], ['cancelado'], true)): ?>
      <form method="post" action="<?= url('/artes/' . $rid . '/comentar') ?>" class="card-footer bg-white"><?= Csrf::field() ?>
        <?php if ($current): ?><input type="hidden" name="version_id" value="<?= (int) $current['id'] ?>"><?php endif; ?>
        <div class="input-group input-group-sm"><input type="text" name="body" class="form-control" placeholder="Comentar<?= $current ? ' sobre a versão ' . (int) $current['version_no'] : '' ?>…" maxlength="3000" required><button class="btn btn-primary">Enviar</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <?php if (Auth::can('art.manage')): ?>
      <form method="post" action="<?= url('/artes/' . $rid . '/designer') ?>" class="card mb-3"><?= Csrf::field() ?>
        <div class="card-body d-flex gap-1"><select name="designer_id" class="form-select form-select-sm"><option value="">— sem designer —</option><?php foreach ($designers as $d): ?><option value="<?= (int) $d['id'] ?>"<?= selected($d['id'], $r['designer_id']) ?>><?= e($d['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Definir</button></div>
      </form>
    <?php endif; ?>

    <?php if ($checklist): $done = count(array_intersect(array_map('intval', array_column($checklist, 'id')), $checks)); ?>
      <form method="post" action="<?= url('/artes/' . $rid . '/checklist') ?>" class="card mb-3<?= $r['status'] === 'aprovacao_midia' && $done < count($checklist) ? ' border-warning' : '' ?>"><?= Csrf::field() ?>
        <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Checklist de identidade visual</span><span class="small <?= $done === count($checklist) ? 'text-success' : 'text-muted' ?>"><?= $done ?>/<?= count($checklist) ?></span></div>
        <div class="card-body small">
          <?php foreach ($checklist as $it): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="items[]" value="<?= (int) $it['id'] ?>" id="ck<?= (int) $it['id'] ?>"<?= checked(in_array((int) $it['id'], $checks, true)) ?>><label class="form-check-label" for="ck<?= (int) $it['id'] ?>"><?= e($it['label']) ?></label></div><?php endforeach; ?>
        </div>
        <div class="card-footer bg-white"><button class="btn btn-sm btn-outline-primary w-100">Salvar checklist</button></div>
      </form>
    <?php endif; ?>

    <?php if (count($versions) > 1): ?>
      <div class="card mb-3"><div class="card-header bg-white fw-semibold">Versões anteriores</div>
        <ul class="list-group list-group-flush small"><?php foreach (array_slice($versions, 1) as $v): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url('/arquivos/' . (int) $v['file_id']) ?>">v<?= (int) $v['version_no'] ?> · <?= e(truncate($v['original_name'], 28)) ?></a><span class="text-muted"><?= e(format_date($v['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($approvals): ?>
      <div class="card mb-3"><div class="card-header bg-white fw-semibold">Aprovações</div>
        <ul class="list-group list-group-flush small"><?php foreach ($approvals as $a): ?><li class="list-group-item"><span class="badge <?= $a['decision'] === 'aprovado' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $a['decision'] === 'aprovado' ? 'Aprovado' : 'Ajustes' ?></span> <?= e(ucfirst($a['stage'])) ?> · <?= e($a['user_name'] ?? '') ?><?= $a['version_no'] ? ' · v' . (int) $a['version_no'] : '' ?><br><span class="text-muted"><?= e(format_datetime($a['created_at'])) ?><?= $a['notes'] ? ' — ' . e($a['notes']) : '' ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($publications): ?>
      <div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Agenda de publicação</span><a href="<?= url('/comunicacao', ['mes' => substr($r['publish_on'], 0, 7)]) ?>" class="small">calendário</a></div>
        <ul class="list-group list-group-flush small"><?php foreach ($publications as $p): ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi <?= Publication::CHANNEL_ICONS[$p['channel']] ?>"></i> <?= e(Publication::CHANNELS[$p['channel']]) ?> · <?= e(format_datetime($p['publish_at'])) ?></span><span class="badge <?= $p['status'] === 'publicado' ? 'text-bg-success' : ($p['status'] === 'cancelado' ? 'text-bg-secondary' : 'text-bg-light border') ?>"><?= e(Publication::STATUSES[$p['status']]) ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </div>
</div>
