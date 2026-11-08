<?php $scripts = ['editor.js']; $pid = (int) $p['id']; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= url('/') ?>">Álbuns</a></li><li class="breadcrumb-item"><a href="<?= url('/albuns/' . (int) $p['album_id'], ['foto' => $pid]) ?>"><?= e($p['album_name']) ?></a></li><li class="breadcrumb-item active">Melhorar foto</li></ol></nav>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0"><i class="bi bi-magic text-ouro"></i> Melhorar foto</h1><div class="text-muted small"><?= e($p['file_name']) ?><?= $p['width'] ? ' · ' . (int) $p['width'] . '×' . (int) $p['height'] : '' ?> · tudo é feito no seu navegador; o original não é alterado</div></div>
  <a href="<?= url('/albuns/' . (int) $p['album_id'], ['foto' => $pid]) ?>" class="btn btn-sm btn-outline-secondary">Voltar ao álbum</a>
</div>
<div class="row g-3" id="editor" data-src="<?= e($source) ?>" data-save-url="<?= url('/fotos/' . $pid . '/versao') ?>" data-can-save="<?= $canSave ? 1 : 0 ?>" data-name="<?= e(pathinfo($p['file_name'], PATHINFO_FILENAME)) ?>" data-album="<?= url('/albuns/' . (int) $p['album_id']) ?>">
  <div class="col-lg-8">
    <div class="editor-stage" id="stage">
      <canvas id="canvas"></canvas>
      <div class="editor-loading" id="loading"><div class="spinner-border text-warning" role="status"></div><div class="mt-2 small">Carregando a foto…</div></div>
      <button type="button" class="btn btn-sm btn-light editor-compare" id="compareBtn" title="Segure para ver o original"><i class="bi bi-eye"></i> Original</button>
    </div>
    <div class="d-flex gap-2 flex-wrap mt-2 small text-muted"><span id="dims"></span><span class="ms-auto">Dica: segure o botão “Original” para comparar antes e depois.</span></div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header fw-semibold"><i class="bi bi-stars"></i> Um toque</div><div class="card-body d-grid gap-2">
      <button type="button" class="btn btn-primary" data-auto><i class="bi bi-magic"></i> Melhorar automaticamente</button>
      <div class="small text-muted">Corrige exposição, contraste e cor pela análise da própria foto (níveis automáticos) e dá leve nitidez.</div>
    </div></div>
    <div class="card mb-3"><div class="card-header fw-semibold"><i class="bi bi-palette"></i> Estilos</div><div class="card-body preset-grid">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="natural">Natural</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="vivido">Vívido</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="culto">Culto (quente)</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="frio">Frio</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="dramatico">Dramático</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="suave">Retrato suave</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="pb">Preto e branco</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="sepia">Sépia</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="cinema">Cinema</button>
    </div></div>
    <div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between"><span><i class="bi bi-sliders"></i> Ajustes finos</span><button type="button" class="btn btn-link btn-sm p-0" data-reset>Zerar tudo</button></div>
      <div class="card-body editor-sliders">
        <?php foreach ([['brightness', 'Brilho', -100, 100], ['contrast', 'Contraste', -100, 100], ['exposure', 'Exposição (luz)', -100, 100], ['highlights', 'Realces (claros)', -100, 100], ['shadows', 'Sombras (escuros)', -100, 100], ['saturation', 'Cor (saturação)', -100, 100], ['vibrance', 'Vivacidade', -100, 100], ['temperature', 'Temperatura (frio ↔ quente)', -100, 100], ['tint', 'Matiz (verde ↔ magenta)', -100, 100], ['clarity', 'Clareza (detalhes)', 0, 100], ['sharpen', 'Nitidez', 0, 100], ['denoise', 'Suavizar ruído', 0, 100], ['vignette', 'Vinheta', 0, 100], ['grain', 'Grão (filme)', 0, 100]] as [$k, $l, $min, $max]): ?>
          <label class="editor-slider"><span><?= e($l) ?><output data-out="<?= $k ?>">0</output></span><input type="range" name="<?= $k ?>" min="<?= $min ?>" max="<?= $max ?>" value="0" step="1" data-adjust="<?= $k ?>"></label>
        <?php endforeach; ?>
        <label class="editor-slider"><span>Girar<output data-out="rotate">0°</output></span><input type="range" name="rotate" min="-180" max="180" value="0" step="90" data-adjust="rotate"></label>
      </div>
    </div>
    <div class="card"><div class="card-body d-grid gap-2">
      <button type="button" class="btn btn-outline-primary" data-download><i class="bi bi-download"></i> Baixar foto melhorada (JPEG)</button>
      <?php if ($canSave): ?>
        <div class="input-group input-group-sm"><input type="text" class="form-control" id="editNote" maxlength="255" placeholder="Anotação (ex.: auto + quente)"><button type="button" class="btn btn-primary" data-save><i class="bi bi-cloud-plus"></i> Salvar no álbum</button></div>
        <div class="small text-muted">Salva como <em>versão editada</em> ao lado do original, na mesma pasta.</div>
      <?php endif; ?>
      <div class="progress usage-bar d-none" id="saveProgress"><div class="progress-bar progress-bar-striped progress-bar-animated" data-width="100"></div></div>
    </div></div>
  </div>
</div>
