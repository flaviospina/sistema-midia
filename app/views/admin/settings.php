<?php $scripts = ['settings.js']; ?>
<h1 class="h4 mb-3">Configurações</h1>
<form method="post" action="<?= url('/admin/configuracoes') ?>" enctype="multipart/form-data" class="row g-3" novalidate><?= Csrf::field() ?>
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header fw-semibold"><i class="bi bi-badge-ad"></i> Logo da igreja e marca d'água</div>
      <div class="card-body">
        <p class="small text-muted">A logo é gravada <strong>na própria foto</strong> no momento da importação (upload ou Google Drive), antes de qualquer ajuste. Depois disso ela acompanha a foto em todos os lugares: galeria, editor, downloads, links públicos e redes sociais. Fotos HEIC não recebem a marca (o servidor não consegue regravá-las).</p>
        <div class="row g-3 align-items-start">
          <div class="col-md-6">
            <label class="form-label" for="logo"><?= $hasLogo ? 'Trocar a logo' : 'Enviar a logo' ?></label>
            <input type="file" class="form-control form-control-sm<?= invalid('logo') ?>" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"><?= field_error('logo') ?>
            <div class="form-text">Prefira PNG com fundo transparente. Lado maior até 1200 px.</div>
            <?php if ($hasLogo): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo"><label class="form-check-label small" for="remove_logo">Remover a logo atual (desliga a marca d'água)</label></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <?php if ($hasLogo): ?><div class="small text-muted mb-1">Pré-visualização com as opções salvas:</div><img src="<?= url('/admin/configuracoes/marca-dagua.jpg', ['v' => time()]) ?>" class="img-fluid rounded border" alt="Pré-visualização da marca d'água"><?php else: ?><div class="text-muted small border rounded p-3 text-center">Nenhuma logo enviada ainda.</div><?php endif; ?>
          </div>
        </div>
        <hr>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="watermark_enabled" value="1" id="watermark_enabled"<?= checked(Setting::get('watermark_enabled', '0') === '1') ?><?= $hasLogo ? '' : ' disabled' ?>><label class="form-check-label fw-semibold" for="watermark_enabled">Aplicar a logo automaticamente nas fotos importadas</label></div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="watermark_position">Posição</label><select class="form-select form-select-sm" id="watermark_position" name="watermark_position"><?php foreach ($positions as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, old('watermark_position', Setting::get('watermark_position'))) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label" for="watermark_width">Tamanho (%)</label><input type="number" class="form-control form-control-sm<?= invalid('watermark_width') ?>" id="watermark_width" name="watermark_width" min="5" max="50" value="<?= e(old('watermark_width', Setting::get('watermark_width'))) ?>"><?= field_error('watermark_width') ?></div>
          <div class="col-md-2"><label class="form-label" for="watermark_opacity">Opacidade (%)</label><input type="number" class="form-control form-control-sm<?= invalid('watermark_opacity') ?>" id="watermark_opacity" name="watermark_opacity" min="10" max="100" value="<?= e(old('watermark_opacity', Setting::get('watermark_opacity'))) ?>"><?= field_error('watermark_opacity') ?></div>
          <div class="col-md-2"><label class="form-label" for="watermark_margin">Margem (%)</label><input type="number" class="form-control form-control-sm<?= invalid('watermark_margin') ?>" id="watermark_margin" name="watermark_margin" min="0" max="10" value="<?= e(old('watermark_margin', Setting::get('watermark_margin'))) ?>"><?= field_error('watermark_margin') ?></div>
        </div>
        <div class="form-text">Tamanho = largura da logo em relação à largura da foto (18% é discreto e legível). Salve para atualizar a pré-visualização.</div>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header fw-semibold"><i class="bi bi-google"></i> Importação do Google Drive</div>
      <div class="card-body">
        <label class="form-label" for="google_api_key">Chave da API do Google (Drive API)</label>
        <input type="text" class="form-control form-control-sm font-monospace<?= invalid('google_api_key') ?>" id="google_api_key" name="google_api_key" value="" placeholder="<?= $driveReady ? '•••••••• chave já configurada; cole outra para trocar' : 'AIza…' ?>" autocomplete="off"><?= field_error('google_api_key') ?>
        <input type="hidden" name="google_api_key_keep" value="<?= $driveReady ? '1' : '0' ?>">
        <?php if ($driveReady): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="removeKey" data-toggle-keep><label class="form-check-label small" for="removeKey">Remover a chave atual</label></div><?php endif; ?>
        <details class="small mt-2"><summary class="cursor-pointer">Como obter a chave (uma vez, gratuito)</summary>
          <ol class="ps-3 mt-2 mb-0">
            <li>Acesse <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> com a conta Google da igreja e crie um projeto (ex.: "Envio de Mídias").</li>
            <li>Menu <em>APIs e serviços → Biblioteca</em>: procure <strong>Google Drive API</strong> e clique em <em>Ativar</em>.</li>
            <li><em>APIs e serviços → Credenciais → Criar credenciais → Chave de API</em>. Copie a chave (começa com "AIza") e cole acima.</li>
            <li>Recomendado: em <em>Restringir chave</em>, limite-a à Google Drive API.</li>
          </ol>
          <p class="mt-2 mb-0">A chave só lê pastas compartilhadas como <strong>"Qualquer pessoa com o link"</strong>; nada é gravado no Drive e nenhuma senha do Google é informada ao sistema.</p>
        </details>
      </div>
    </div>
    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar configurações</button>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold"><i class="bi bi-images"></i> Fotos já importadas</div>
      <div class="card-body small">
        <p>Fotos importadas antes da logo não têm a marca d'água. Você pode aplicá-la agora: <strong id="unstampedCount"><?= (int) $unstamped ?></strong> foto(s) sem marca.</p>
        <button type="button" class="btn btn-sm btn-outline-primary" id="stampBtn" data-url="<?= url('/admin/configuracoes/aplicar-marca') ?>"<?= $unstamped && $hasLogo && Setting::get('watermark_enabled', '0') === '1' ? '' : ' disabled' ?>><i class="bi bi-magic"></i> Aplicar a marca nas fotos antigas</button>
        <div class="progress usage-bar mt-2 d-none" id="stampProgress"><div class="progress-bar progress-bar-striped progress-bar-animated" data-width="100"></div></div>
        <div class="text-muted mt-2" id="stampStatus"><?= $hasLogo && Setting::get('watermark_enabled', '0') === '1' ? 'Processa em lotes; mantenha a página aberta até terminar.' : 'Ative a marca d\'água e salve para liberar este botão.' ?></div>
        <p class="text-muted mt-3 mb-0">Atenção: a marca é gravada na própria foto e não pode ser removida depois.</p>
      </div>
    </div>
  </div>
</form>
