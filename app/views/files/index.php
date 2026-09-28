<?php $fid = $folder ? (int) $folder['id'] : null; $qs = static fn(array $extra): string => http_build_query(array_filter(['ordem' => $order] + $extra)); ?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= url('/arquivos') ?>"><i class="bi bi-hdd-stack"></i> Arquivos</a></li>
    <?php foreach ($crumbs as $c): ?>
      <li class="breadcrumb-item<?= (int) $c['id'] === $fid ? ' active' : '' ?>"><?php if ((int) $c['id'] === $fid): ?><?= e($c['name']) ?><?php else: ?><a href="<?= url('/pastas/' . (int) $c['id']) ?>"><?= e($c['name']) ?></a><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e($folder ? $folder['name'] : 'Arquivos') ?></h1>
    <?php if ($folder): ?>
      <div class="small text-muted">
        <i class="bi bi-eye"></i> <?= e(Folder::VISIBILITIES[$effective['visibility']]) ?><?= $folder['visibility'] === null ? ' (herdada)' : '' ?>
        <?php if ($effective['ministry_id']): ?> · <?= e(Ministry::find((int) $effective['ministry_id'])['name'] ?? '') ?><?php endif; ?>
        <?php if ($folder['description']): ?> · <?= e($folder['description']) ?><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <form class="d-flex" method="get" action="<?= url('/arquivos/buscar') ?>">
      <?php if ($fid): ?><input type="hidden" name="pasta" value="<?= $fid ?>"><?php endif; ?>
      <input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar<?= $fid ? ' nesta pasta' : '' ?>…">
      <button class="btn btn-sm btn-outline-secondary ms-1" aria-label="Buscar"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($canUpload): ?>
      <a href="<?= url('/arquivos/enviar', $fid ? ['pasta' => $fid] : []) ?>" class="btn btn-sm btn-primary"><i class="bi bi-cloud-arrow-up"></i> Enviar</a>
    <?php endif; ?>
    <?php if (Auth::can('folders.manage')): ?>
      <a href="<?= url('/pastas/nova', $fid ? ['pasta' => $fid] : []) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-folder-plus"></i> Pasta</a>
      <?php if ($folder): ?><a href="<?= url('/pastas/' . $fid . '/editar') ?>" class="btn btn-sm btn-outline-secondary" title="Editar pasta"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php endif; ?>
    <?php if ($folder && $files && Zipper::available()): ?>
      <a href="<?= url('/pastas/' . $fid . '/zip') ?>" class="btn btn-sm btn-outline-secondary" title="Baixar pasta em ZIP"><i class="bi bi-file-earmark-zip"></i></a>
    <?php endif; ?>
    <?php if ($folder && Auth::can('files.share') && $effective['visibility'] !== 'restrito'): ?>
      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#shareBox" title="Compartilhar pasta"><i class="bi bi-link-45deg"></i></button>
    <?php endif; ?>
    <div class="btn-group btn-group-sm">
      <a href="?<?= $qs(['visao' => 'grade']) ?>" class="btn btn-outline-secondary<?= $view === 'grade' ? ' active' : '' ?>" title="Grade"><i class="bi bi-grid-3x3-gap"></i></a>
      <a href="?<?= $qs(['visao' => 'lista']) ?>" class="btn btn-outline-secondary<?= $view === 'lista' ? ' active' : '' ?>" title="Lista"><i class="bi bi-list-ul"></i></a>
    </div>
  </div>
</div>

<?php if (!empty($_SESSION['_share_url'])): $su = $_SESSION['_share_url']; unset($_SESSION['_share_url']); ?>
  <div class="alert alert-success d-flex align-items-center gap-2 flex-wrap">
    <span>Link:</span><input type="text" id="shareUrl" class="form-control form-control-sm w-auto flex-grow-1 share-url" value="<?= e($su) ?>" readonly>
    <button class="btn btn-sm btn-outline-dark" type="button" data-copy-input="shareUrl"><i class="bi bi-clipboard"></i> Copiar</button>
  </div>
<?php endif; ?>

<?php if ($folder && Auth::can('files.share')): ?>
<div class="collapse mb-3" id="shareBox">
  <form method="post" action="<?= url('/compartilhamentos') ?>" class="card card-body row g-2 align-items-end">
    <?= Csrf::field() ?><input type="hidden" name="folder_id" value="<?= $fid ?>">
    <div class="col-sm-4"><label class="form-label small">Descrição do link</label><input type="text" name="label" class="form-control form-control-sm" maxlength="150" placeholder="Ex.: fotos do congresso"></div>
    <div class="col-sm-3"><label class="form-label small">Validade (dias)</label><input type="number" name="days" class="form-control form-control-sm" value="7" min="1" max="365"></div>
    <div class="col-sm-3"><label class="form-label small">Máx. downloads (vazio = sem limite)</label><input type="number" name="max_downloads" class="form-control form-control-sm" min="1"></div>
    <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Gerar link</button></div>
    <?php if ($links): ?>
      <div class="col-12 small">
        <?php foreach ($links as $l): if (!ShareLink::isValid($l)) continue; ?>
          <div class="d-flex align-items-center gap-2 mt-1"><code class="share-url"><?= e(absolute_url('/compartilhar/' . $l['token'])) ?></code>
            <span class="text-muted">até <?= e(format_date($l['expires_at'])) ?> · <?= (int) $l['downloads'] ?> download(s)</span>
            <button class="btn btn-sm btn-link text-danger p-0" form="deact<?= (int) $l['id'] ?>">desativar</button></div>
          <form id="deact<?= (int) $l['id'] ?>" method="post" action="<?= url('/compartilhamentos/' . (int) $l['id'] . '/desativar') ?>"><?= Csrf::field() ?></form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<?php if ($folders): ?>
  <div class="row g-2 mb-3">
    <?php foreach ($folders as $f): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= url('/pastas/' . (int) $f['id']) ?>" class="folder-card">
          <i class="bi bi-folder-fill"></i>
          <span class="min-w-0"><span class="d-block fw-semibold text-truncate"><?= e($f['name']) ?></span>
            <?php if ($f['visibility']): ?><span class="small text-muted"><?= e(Folder::VISIBILITIES[$f['visibility']]) ?></span><?php endif; ?></span>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php elseif (!$folder): ?>
  <div class="text-muted small mb-3">Nenhuma pasta visível para o seu perfil.</div>
<?php endif; ?>

<?php if ($folder): ?>
  <?php if (!$files): ?>
    <div class="card"><div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block"></i>Nenhum arquivo nesta pasta.</div></div>
  <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
      <span><?= count($files) ?> arquivo(s)</span>
      <span>Ordenar:
        <?php foreach (['recentes' => 'recentes', 'antigos' => 'antigos', 'nome' => 'nome', 'tamanho' => 'tamanho'] as $k => $l): ?>
          <a href="?<?= http_build_query(['ordem' => $k]) ?>" class="<?= $order === $k ? 'fw-bold' : '' ?>"><?= $l ?></a><?= $k !== 'tamanho' ? ' · ' : '' ?>
        <?php endforeach; ?>
      </span>
    </div>
    <?php if ($view === 'lista'): ?>
      <div class="card"><div class="table-responsive"><table class="table table-hover table-files mb-0">
        <thead class="table-light"><tr><th class="text-center"><input type="checkbox" class="form-check-input" id="selectAll" aria-label="Selecionar todos"></th><th>Nome</th><th>Tipo</th><th>Tamanho</th><th>Enviado em</th><th>Por</th><th>Tags</th></tr></thead>
        <tbody><?php foreach ($files as $file) partial('file_row', ['file' => $file]); ?></tbody>
      </table></div></div>
    <?php else: ?>
      <div class="file-grid"><?php foreach ($files as $file) partial('file_card', ['file' => $file]); ?></div>
    <?php endif; ?>
  <?php endif; ?>
<?php else: ?>
  <?php if ($mine): ?>
    <h2 class="h6 text-muted mt-3">Meus envios recentes</h2>
    <div class="file-grid"><?php foreach ($mine as $file) partial('file_card', ['file' => $file, 'selectable' => false]); ?></div>
  <?php endif; ?>
  <?php if (!empty($tags)): ?>
    <div class="mt-3 small"><span class="text-muted">Tags:</span>
      <?php foreach ($tags as $t): ?><a href="<?= url('/arquivos/buscar', ['tag' => $t['slug']]) ?>" class="badge text-bg-light border text-decoration-none"><?= e($t['name']) ?> <span class="text-muted"><?= (int) $t['qty'] ?></span></a> <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (Zipper::available()): ?>
<div id="selectionBar" class="selection-bar d-none mt-3">
  <form id="zipForm" method="post" action="<?= url('/arquivos/zip') ?>" class="card card-body py-2 d-flex flex-row align-items-center gap-2 shadow">
    <?= Csrf::field() ?>
    <span><strong data-count>0</strong> selecionado(s)</span>
    <button class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-zip"></i> Baixar em ZIP</button>
    <button class="btn btn-sm btn-link" type="button" data-clear>limpar</button>
  </form>
</div>
<?php endif; ?>
