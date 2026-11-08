<?php $scripts = ['drive.js']; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= url('/') ?>">Álbuns</a></li><li class="breadcrumb-item"><a href="<?= url('/albuns/' . (int) $a['id']) ?>"><?= e($a['name']) ?></a></li><li class="breadcrumb-item active">Importar do Google Drive</li></ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0"><i class="bi bi-google text-ouro"></i> Importar do Google Drive</h1><div class="text-muted small">Destino: <span class="font-monospace"><?= e($a['folder']) ?></span></div></div>
  <div class="d-flex gap-2"><a href="<?= url('/albuns/' . (int) $a['id'] . '/enviar') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-cloud-arrow-up"></i> Enviar do aparelho</a><a href="<?= url('/albuns/' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-images"></i> Ver álbum</a></div>
</div>
<?php if (!$ready): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> A importação do Drive ainda não está configurada. <?= $isAdmin ? 'Informe a chave da API do Google em <a href="' . url('/admin/configuracoes') . '" class="alert-link">Configurações</a>.' : 'Peça ao administrador para informar a chave da API do Google em Configurações.' ?></div>
<?php else: ?>
<div id="drive" data-album="<?= (int) $a['id'] ?>" data-list="<?= url('/api/drive/listar') ?>" data-import="<?= url('/api/drive/importar') ?>" data-album-url="<?= url('/albuns/' . (int) $a['id']) ?>">
  <div class="card mb-3"><div class="card-body">
    <label class="form-label fw-semibold" for="driveLink">Link da pasta compartilhada</label>
    <div class="input-group"><input type="url" class="form-control" id="driveLink" placeholder="https://drive.google.com/drive/folders/…" autocomplete="off"><button type="button" class="btn btn-primary" id="listBtn"><i class="bi bi-search"></i> Listar fotos</button></div>
    <div class="form-text">No Google Drive: botão direito na pasta → <em>Compartilhar</em> → em <em>Acesso geral</em> escolha <strong>Qualquer pessoa com o link</strong> (Leitor) → <em>Copiar link</em> e cole aqui. Subpastas não são lidas.</div>
    <div class="small text-danger mt-2 d-none" id="driveError"></div>
  </div></div>
  <div class="card d-none" id="resultCard">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="fw-semibold"><i class="bi bi-folder2-open"></i> <span id="folderName"></span> · <span id="fileCount"></span></span>
      <div class="d-flex gap-2"><div class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" id="skipImported" checked><label class="form-check-label" for="skipImported">Pular as já importadas</label></div><button type="button" class="btn btn-sm btn-primary" id="importBtn"><i class="bi bi-download"></i> Importar <span id="importCount"></span></button></div></div>
    <div class="card-body py-2 d-none" id="progressWrap"><div class="d-flex justify-content-between small"><span id="progressText">Importando…</span><span id="progressPct"></span></div><div class="progress usage-bar"><div class="progress-bar" id="progressBar"></div></div></div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 small"><thead class="table-light"><tr><th>Arquivo</th><th>Tamanho</th><th>Dimensões</th><th>Situação</th></tr></thead><tbody id="fileRows"></tbody></table></div>
    <div class="card-footer small text-muted d-none" id="doneFooter"></div>
  </div>
</div>
<?php endif; ?>
