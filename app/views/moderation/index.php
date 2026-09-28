<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Quarentena <span class="badge text-bg-warning"><?= count($files) ?></span></h1>
  <a href="<?= url('/arquivos/lixeira') ?>" class="btn btn-sm btn-outline-secondary">Rejeitados e lixeira</a>
</div>
<?php if (!$files): ?>
  <div class="card"><div class="card-body text-center text-muted py-4"><i class="bi bi-shield-check fs-2 d-block"></i>Nada aguardando moderação.</div></div>
<?php endif; ?>
<?php foreach ($files as $f): $fid = (int) $f['id']; ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <a href="<?= url('/arquivos/' . $fid) ?>" class="file-card d-block">
            <span class="file-thumb"><?php if ($f['thumb_ref']): ?><img src="<?= url('/arquivos/' . $fid . '/miniatura') ?>" alt=""><?php else: ?><i class="bi <?= FileTypes::icon($f['category'], $f['extension']) ?>"></i><?php endif; ?></span>
          </a>
          <div class="small mt-1 text-break"><strong><?= e($f['original_name']) ?></strong><br><?= e(format_bytes((int) $f['size_bytes'])) ?> · <?= e(FileTypes::CATEGORIES[$f['category']]) ?><?= $f['width'] ? ' · ' . (int) $f['width'] . '×' . (int) $f['height'] : '' ?><?= $f['duration_seconds'] ? ' · ' . e(format_duration((int) $f['duration_seconds'])) : '' ?></div>
          <div class="small text-muted">
            <?php if ($f['guest_upload_id']): ?>
              <i class="bi bi-person"></i> <?= e($f['guest_name']) ?> (convidado) · <a href="<?= e(whatsapp_link($f['guest_whatsapp'])) ?>" target="_blank" rel="noopener"><?= e(format_phone($f['guest_whatsapp'])) ?></a>
              <?= $f['guest_ministry'] ? '<br><i class="bi bi-diagram-3"></i> ' . e($f['guest_ministry']) : '' ?>
              <?= $f['guest_event'] ? '<br><i class="bi bi-calendar-event"></i> ' . e($f['guest_event']) : '' ?>
              <?= $f['guest_description'] ? '<br><em>' . e($f['guest_description']) . '</em>' : '' ?>
            <?php else: ?>
              <i class="bi bi-person"></i> <?= e($f['uploader_name'] ?? '—') ?><?= $f['event_name'] ? '<br><i class="bi bi-calendar-event"></i> ' . e($f['event_name']) : '' ?><?= $f['description'] ? '<br><em>' . e($f['description']) . '</em>' : '' ?>
            <?php endif; ?>
            <br><?= e(format_datetime($f['created_at'])) ?> · IP <?= e($f['upload_ip']) ?>
          </div>
        </div>
        <div class="col-md-6">
          <form method="post" action="<?= url('/moderacao/' . $fid . '/aprovar') ?>" class="row g-2">
            <?= Csrf::field() ?>
            <div class="col-12"><select name="folder_id" class="form-select form-select-sm" required><option value="">Pasta de destino…</option><?php foreach ($folders as $id => $label): ?><option value="<?= (int) $id ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><input type="text" name="title" class="form-control form-control-sm" placeholder="Título (opcional)" maxlength="200"></div>
            <div class="col-6"><select name="category" class="form-select form-select-sm"><?php foreach (FileTypes::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['category']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><input type="text" name="tags" class="form-control form-control-sm" placeholder="Tags separadas por vírgula"></div>
            <div class="col-12"><select name="event_id" class="form-select form-select-sm"><option value="">Evento: <?= e($f['guest_event'] ?? $f['event_name'] ?? 'nenhum') ?></option><?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"><?= e(Event::label($ev)) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 small">
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="has_restriction" value="1" id="hr<?= $fid ?>"><label class="form-check-label" for="hr<?= $fid ?>">Contém pessoa com restrição de imagem</label></div>
              <?php if ($restrictions): ?>
                <select name="restrictions[]" class="form-select form-select-sm mt-1" multiple size="3" aria-label="Pessoas com restrição">
                  <?php foreach ($restrictions as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['person_name']) ?><?= $r['is_minor'] ? ' (menor)' : '' ?></option><?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
            <div class="col-12"><button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Aprovar</button></div>
          </form>
        </div>
        <div class="col-md-3">
          <form method="post" action="<?= url('/moderacao/' . $fid . '/rejeitar') ?>" data-confirm="Rejeitar este arquivo?">
            <?= Csrf::field() ?>
            <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Motivo da rejeição" maxlength="500" required>
            <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-x-lg"></i> Rejeitar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
