<h1 class="h4 mb-3">Buscar arquivos</h1>
<form class="card mb-3" method="get" action="<?= url('/arquivos/buscar') ?>">
  <div class="card-body row g-2">
    <div class="col-12 col-md-4"><input type="search" name="q" class="form-control form-control-sm" value="<?= e($filters['q']) ?>" placeholder="Nome, título, descrição ou evento"></div>
    <div class="col-6 col-md-2"><input type="text" name="tag" class="form-control form-control-sm" value="<?= e($filters['tag']) ?>" placeholder="Tag"></div>
    <div class="col-6 col-md-2">
      <select name="tipo" class="form-select form-select-sm"><option value="">Todos os tipos</option>
        <?php foreach (FileTypes::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['category']) ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2"><input type="text" name="evento" class="form-control form-control-sm" value="<?= e($filters['event']) ?>" placeholder="Evento"></div>
    <div class="col-6 col-md-2">
      <select name="pasta" class="form-select form-select-sm"><option value="">Todas as pastas</option>
        <?php foreach ($folders as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected($id, $filters['folder_id']) ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="ministerio" class="form-select form-select-sm"><option value="">Todos os ministérios</option>
        <?php foreach ($ministries as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], $filters['ministry_id']) ?>><?= e($m['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($uploaders): ?>
    <div class="col-6 col-md-2">
      <select name="quem" class="form-select form-select-sm"><option value="">Quem enviou</option>
        <?php foreach ($uploaders as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], $filters['uploader']) ?>><?= e($u['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><input type="date" name="de" class="form-control form-control-sm" value="<?= e($filters['from']) ?>" aria-label="De"></div>
    <div class="col-6 col-md-2"><input type="date" name="ate" class="form-control form-control-sm" value="<?= e($filters['to']) ?>" aria-label="Até"></div>
    <div class="col-6 col-md-2">
      <select name="ordem" class="form-select form-select-sm">
        <?php foreach (['recentes' => 'Mais recentes', 'antigos' => 'Mais antigos', 'nome' => 'Nome', 'tamanho' => 'Tamanho'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $filters['order']) ?>><?= $l ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 d-flex gap-1">
      <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> Buscar</button>
      <div class="btn-group btn-group-sm">
        <button name="visao" value="grade" class="btn btn-outline-secondary<?= $view === 'grade' ? ' active' : '' ?>" title="Grade"><i class="bi bi-grid-3x3-gap"></i></button>
        <button name="visao" value="lista" class="btn btn-outline-secondary<?= $view === 'lista' ? ' active' : '' ?>" title="Lista"><i class="bi bi-list-ul"></i></button>
      </div>
    </div>
  </div>
</form>

<?php if (!$result['itens']): ?>
  <div class="card"><div class="card-body text-center text-muted py-4">Nenhum arquivo encontrado.</div></div>
<?php elseif ($view === 'lista'): ?>
  <div class="card"><div class="table-responsive"><table class="table table-hover table-files mb-0">
    <thead class="table-light"><tr><th class="text-center"><input type="checkbox" class="form-check-input" id="selectAll" aria-label="Selecionar todos"></th><th>Nome</th><th>Tipo</th><th>Tamanho</th><th>Enviado em</th><th>Por</th><th>Tags</th></tr></thead>
    <tbody><?php foreach ($result['itens'] as $file) partial('file_row', ['file' => $file]); ?></tbody>
  </table></div></div>
<?php else: ?>
  <div class="file-grid"><?php foreach ($result['itens'] as $file) partial('file_card', ['file' => $file]); ?></div>
<?php endif; ?>
<?php partial('pagination', ['result' => $result]) ?>

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
