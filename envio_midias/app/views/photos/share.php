<?php $pid = (int) $p['id']; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= url('/') ?>">Álbuns</a></li><li class="breadcrumb-item"><a href="<?= url('/albuns/' . (int) $p['album_id'], ['foto' => $pid]) ?>"><?= e($p['album_name']) ?></a></li><li class="breadcrumb-item active">Publicar nas redes</li></ol></nav>
<h1 class="h4 mb-3"><i class="bi bi-share text-ouro"></i> Publicar nas redes sociais</h1>
<div class="row g-3">
  <div class="col-md-5"><div class="card"><img src="<?= url('/fotos/' . $pid . '/imagem') ?>" class="card-img-top" alt=""><div class="card-body small text-muted"><?= e($p['file_name']) ?><?= $p['width'] ? ' · ' . (int) $p['width'] . '×' . (int) $p['height'] : '' ?><br>A publicação usa a versão de exibição (até <?= DISPLAY_MAX_SIDE ?> px, sem dados de localização).</div>
    <div class="card-footer d-grid"><a href="<?= url('/fotos/' . $pid . '/melhorar') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-magic"></i> Melhorar antes de publicar</a></div></div></div>
  <div class="col-md-7">
    <?php if (!$accounts): ?>
      <div class="alert alert-info"><i class="bi bi-info-circle"></i> Você ainda não conectou nenhuma rede social. Conecte a sua conta em <a href="<?= url('/meus-dados') ?>" class="alert-link">Meus dados → Redes sociais</a>. Cada pessoa usa as próprias credenciais.</div>
    <?php else: ?>
    <form method="post" action="<?= url('/fotos/' . $pid . '/compartilhar') ?>" class="card" data-confirm="Publicar agora nas redes selecionadas?" data-confirm-ok="Publicar"><?= Csrf::field() ?>
      <div class="card-body">
        <label class="form-label fw-semibold">Publicar em</label>
        <?php foreach (SocialAccount::NETWORKS as $k => $l): if (!isset($accounts[$k])) continue; $acc = $accounts[$k]; $expired = $acc['token_expires_at'] && $acc['token_expires_at'] < date('Y-m-d H:i:s'); ?>
          <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="networks[]" value="<?= e($k) ?>" id="n_<?= e($k) ?>"<?= checked(!$expired) ?><?= $expired ? ' disabled' : '' ?>><label class="form-check-label" for="n_<?= e($k) ?>"><i class="bi bi-<?= $k ?>"></i> <?= e($l) ?> <span class="text-muted">(<?= e($acc['account_name'] ?? $acc['account_id']) ?>)</span><?= $expired ? ' <span class="badge text-bg-danger">token expirado</span>' : '' ?></label></div>
        <?php endforeach; ?>
        <label class="form-label fw-semibold mt-3" for="caption">Legenda</label>
        <textarea class="form-control" id="caption" name="caption" rows="6" maxlength="2200"><?= e(old('caption', $defaultCaption)) ?></textarea>
        <div class="form-text">Até 2.200 caracteres. Hashtags e quebras de linha são permitidas.</div>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center"><span class="small text-muted">Publica imediatamente nas contas marcadas.</span><button class="btn btn-primary"><i class="bi bi-send"></i> Publicar agora</button></div>
    </form>
    <?php endif; ?>
    <div class="card mt-3"><div class="card-body small">
      <strong><i class="bi bi-phone"></i> Pelo celular, sem configurar nada:</strong> abra a foto no álbum e toque em <em>Compartilhar</em>. O celular oferece Instagram, WhatsApp, Facebook e outros aplicativos instalados, com a imagem já anexada (recurso de compartilhamento do próprio aparelho).
    </div></div>
    <?php if ($posts): ?>
      <div class="card mt-3"><div class="card-header fw-semibold">Histórico desta foto</div><ul class="list-group list-group-flush small">
        <?php foreach ($posts as $s): ?><li class="list-group-item d-flex justify-content-between gap-2"><span><i class="bi bi-<?= e($s['network']) ?>"></i> <?= e(SocialAccount::NETWORKS[$s['network']]) ?> · <?= e($s['user_name']) ?> · <?= e(format_datetime($s['created_at'])) ?><?= $s['error'] ? '<br><span class="text-danger">' . e($s['error']) . '</span>' : '' ?></span><span><?= $s['status'] === 'publicado' ? '<span class="badge text-bg-success">publicado</span>' : '<span class="badge text-bg-danger">falhou</span>' ?><?= $s['permalink'] ? ' <a href="' . e($s['permalink']) . '" target="_blank" rel="noopener">abrir</a>' : '' ?></span></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
  </div>
</div>
