<?php $fid = (int) $f['id']; $ext = $f['extension']; ?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= url('/arquivos') ?>">Arquivos</a></li>
    <?php foreach ($crumbs as $c): ?><li class="breadcrumb-item"><a href="<?= url('/pastas/' . (int) $c['id']) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
    <?php if (!$crumbs && $f['status'] === 'quarentena'): ?><li class="breadcrumb-item"><a href="<?= url('/moderacao') ?>">Quarentena</a></li><?php endif; ?>
    <li class="breadcrumb-item active"><?= e(truncate($f['original_name'], 40)) ?></li>
  </ol>
</nav>

<?php if (!empty($_SESSION['_share_url'])): $su = $_SESSION['_share_url']; unset($_SESSION['_share_url']); ?>
  <div class="alert alert-success d-flex align-items-center gap-2 flex-wrap">
    <span>Link:</span><input type="text" id="shareUrl" class="form-control form-control-sm w-auto flex-grow-1 share-url" value="<?= e($su) ?>" readonly>
    <button class="btn btn-sm btn-outline-dark" type="button" data-copy-input="shareUrl"><i class="bi bi-clipboard"></i> Copiar</button>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="preview-box mb-2">
      <?php if (FileTypes::isImage($ext)): ?>
        <img src="<?= url('/arquivos/' . $fid . '/ver') ?>" alt="<?= e($f['title'] ?: $f['original_name']) ?>">
      <?php elseif (FileTypes::isVideo($ext) && $ext !== 'mov'): ?>
        <video controls preload="metadata" <?= $f['thumb_ref'] ? 'poster="' . url('/arquivos/' . $fid . '/miniatura') . '"' : '' ?>><source src="<?= url('/arquivos/' . $fid . '/ver') ?>" type="video/mp4">Seu navegador não reproduz este vídeo.</video>
      <?php elseif (FileTypes::isAudio($ext)): ?>
        <div class="p-4 w-100 text-center"><i class="bi bi-music-note-beamed text-white fs-1 d-block mb-2"></i><audio controls preload="metadata" class="w-100"><source src="<?= url('/arquivos/' . $fid . '/ver') ?>"></audio></div>
      <?php elseif ($ext === 'pdf'): ?>
        <iframe src="<?= url('/arquivos/' . $fid . '/ver') ?>" title="PDF"></iframe>
      <?php else: ?>
        <div class="text-center text-white-50 p-4">
          <?php if ($f['thumb_ref']): ?><img src="<?= url('/arquivos/' . $fid . '/miniatura') ?>" alt="" class="mb-2"><?php else: ?><i class="bi <?= FileTypes::icon($f['category'], $ext) ?> fs-1 d-block"></i><?php endif; ?>
          <div class="small">Sem pré-visualização para .<?= e($ext) ?><?= $ext === 'mov' ? ' (baixe para assistir)' : '' ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <a href="<?= url('/arquivos/' . $fid . '/download') ?>" class="btn btn-primary btn-sm"><i class="bi bi-download"></i> Baixar<?= $f['display_ref'] && !Auth::can('files.original') ? ' (sem EXIF)' : '' ?></a>
      <?php if (Auth::can('files.original') && $f['display_ref']): ?><a href="<?= url('/arquivos/' . $fid . '/original') ?>" class="btn btn-outline-secondary btn-sm" title="Original com EXIF/GPS"><i class="bi bi-file-earmark-lock"></i> Original</a><?php endif; ?>
      <?php if ($canManage): ?>
        <a href="<?= url('/arquivos/' . $fid . '/editar') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        <?php if ($f['status'] !== 'lixeira'): ?>
        <form method="post" action="<?= url('/arquivos/' . $fid . '/lixeira') ?>" data-confirm="Mover este arquivo para a lixeira?"><?= Csrf::field() ?><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Lixeira</button></form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($f['status'] === 'lixeira' && Auth::can('files.moderate')): ?>
        <form method="post" action="<?= url('/arquivos/' . $fid . '/restaurar') ?>"><?= Csrf::field() ?><button class="btn btn-success btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button></form>
      <?php endif; ?>
      <?php if ($f['status'] === 'quarentena' && Auth::can('files.moderate')): ?>
        <a href="<?= url('/moderacao') ?>" class="btn btn-warning btn-sm"><i class="bi bi-shield-check"></i> Moderar</a>
      <?php endif; ?>
      <?php if (Auth::can('files.share') && $f['status'] === 'aprovado' && (int) $f['has_restriction'] === 0 && $visibility !== 'restrito'): ?>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#shareBox"><i class="bi bi-link-45deg"></i> Compartilhar</button>
      <?php endif; ?>
    </div>

    <?php if (Auth::can('files.share')): ?>
    <div class="collapse mb-3" id="shareBox">
      <form method="post" action="<?= url('/compartilhamentos') ?>" class="card card-body row g-2 align-items-end">
        <?= Csrf::field() ?><input type="hidden" name="file_id" value="<?= $fid ?>">
        <div class="col-sm-4"><label class="form-label small">Descrição</label><input type="text" name="label" class="form-control form-control-sm" maxlength="150"></div>
        <div class="col-sm-3"><label class="form-label small">Validade (dias)</label><input type="number" name="days" class="form-control form-control-sm" value="7" min="1" max="365"></div>
        <div class="col-sm-3"><label class="form-label small">Máx. downloads</label><input type="number" name="max_downloads" class="form-control form-control-sm" min="1" placeholder="sem limite"></div>
        <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Gerar link</button></div>
        <?php foreach ($links as $l): if (!ShareLink::isValid($l)) continue; ?>
          <div class="col-12 small d-flex align-items-center gap-2"><code class="share-url"><?= e(absolute_url('/compartilhar/' . $l['token'])) ?></code>
            <span class="text-muted">até <?= e(format_date($l['expires_at'])) ?> · <?= (int) $l['downloads'] ?><?= $l['max_downloads'] ? '/' . (int) $l['max_downloads'] : '' ?> download(s)</span>
            <button class="btn btn-sm btn-link text-danger p-0" form="deact<?= (int) $l['id'] ?>">desativar</button></div>
          <form id="deact<?= (int) $l['id'] ?>" method="post" action="<?= url('/compartilhamentos/' . (int) $l['id'] . '/desativar') ?>"><?= Csrf::field() ?></form>
        <?php endforeach; ?>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($f['description']): ?><div class="card mb-3"><div class="card-body"><?= nl2br(e($f['description'])) ?></div></div><?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-body">
        <h1 class="h5"><?= e($f['title'] ?: $f['original_name']) ?></h1>
        <?php if ($f['status'] !== 'aprovado'): ?><span class="badge text-bg-warning mb-2"><?= e(MediaFile::STATUSES[$f['status']]) ?></span><?php endif; ?>
        <?php if ((int) $f['has_restriction'] === 1): ?><span class="badge text-bg-danger mb-2"><i class="bi bi-eye-slash"></i> Contém pessoa com restrição de imagem</span><?php endif; ?>
        <?php if ($f['status'] === 'rejeitado' && $f['reject_reason']): ?><div class="alert alert-secondary small py-2">Motivo: <?= e($f['reject_reason']) ?></div><?php endif; ?>
        <dl class="row small mb-0">
          <dt class="col-5">Arquivo</dt><dd class="col-7 text-break"><?= e($f['original_name']) ?></dd>
          <dt class="col-5">Tipo</dt><dd class="col-7"><?= e(FileTypes::CATEGORIES[$f['category']]) ?> · .<?= e($ext) ?></dd>
          <dt class="col-5">Tamanho</dt><dd class="col-7"><?= e(format_bytes((int) $f['size_bytes'])) ?></dd>
          <?php if ($f['width']): ?><dt class="col-5">Dimensões</dt><dd class="col-7"><?= (int) $f['width'] ?> × <?= (int) $f['height'] ?></dd><?php endif; ?>
          <?php if ($f['duration_seconds']): ?><dt class="col-5">Duração</dt><dd class="col-7"><?= e(format_duration((int) $f['duration_seconds'])) ?></dd><?php endif; ?>
          <dt class="col-5">Pasta</dt><dd class="col-7"><?= e(Folder::pathLabel($f['folder_id'] ? (int) $f['folder_id'] : null)) ?></dd>
          <dt class="col-5">Visibilidade</dt><dd class="col-7"><?= e(Folder::VISIBILITIES[$visibility]) ?><?= $f['visibility'] === null && (int) $f['has_restriction'] === 0 ? ' <span class="text-muted">(herdada)</span>' : '' ?></dd>
          <?php if ($f['event_name']): ?><dt class="col-5">Evento</dt><dd class="col-7"><?= e($f['event_name']) ?></dd><?php endif; ?>
          <dt class="col-5">Enviado por</dt><dd class="col-7"><?= e($f['uploader_name'] ?? ($f['guest_name'] ? $f['guest_name'] . ' (convidado)' : '—')) ?><br><span class="text-muted"><?= e(format_datetime($f['created_at'])) ?></span></dd>
          <?php if ($f['guest_upload_id'] && Auth::can('files.moderate')): ?>
            <dt class="col-5">Convidado</dt><dd class="col-7"><?= e(format_phone($f['guest_whatsapp'])) ?><?= $f['guest_ministry'] ? '<br>' . e($f['guest_ministry']) : '' ?><?= $f['guest_description'] ? '<br><em>' . e($f['guest_description']) . '</em>' : '' ?></dd>
          <?php endif; ?>
          <dt class="col-5">Downloads</dt><dd class="col-7"><?= (int) $downloadCount ?></dd>
          <?php if (Auth::can('files.moderate')): ?><dt class="col-5">SHA-256</dt><dd class="col-7 text-break"><code class="small"><?= e(substr($f['sha256'], 0, 16)) ?>…</code></dd><?php endif; ?>
        </dl>
        <?php if ($f['tags']): ?><div class="mt-2"><?php foreach ($f['tags'] as $t): ?><a href="<?= url('/arquivos/buscar', ['tag' => $t['slug']]) ?>" class="badge text-bg-light border text-decoration-none"><?= e($t['name']) ?></a> <?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>

    <?php if ($restrictions): ?>
      <div class="card mb-3 border-danger-subtle"><div class="card-header bg-white fw-semibold small">Pessoas com restrição neste arquivo</div>
        <ul class="list-group list-group-flush small"><?php foreach ($restrictions as $r): ?><li class="list-group-item"><?= e($r['person_name']) ?><?= $r['is_minor'] ? ' <span class="badge text-bg-secondary">menor</span>' : '' ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($duplicates): ?>
      <div class="card mb-3"><div class="card-header bg-white fw-semibold small">Cópias idênticas</div>
        <ul class="list-group list-group-flush small"><?php foreach ($duplicates as $d): ?><li class="list-group-item"><a href="<?= url('/arquivos/' . (int) $d['id']) ?>"><?= e($d['original_name']) ?></a> <span class="text-muted">· <?= e(Folder::pathLabel($d['folder_id'] ? (int) $d['folder_id'] : null)) ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($downloads): ?>
      <div class="card"><div class="card-header bg-white fw-semibold small">Últimos downloads</div>
        <ul class="list-group list-group-flush small"><?php foreach ($downloads as $d): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($d['user_name'] ?? ($d['share_link_id'] ? 'via link' : 'anônimo')) ?> <span class="text-muted">· <?= e($d['kind']) ?></span></span><span class="text-muted"><?= e(format_datetime($d['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </div>
</div>
