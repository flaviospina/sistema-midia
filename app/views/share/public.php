<?php $t = $link['token']; ?>
<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1"><?= e($link['label'] ?: ($folder ? $folder['name'] : 'Arquivo compartilhado')) ?></h2>
    <div class="text-muted small mb-3">Disponível até <?= e(format_datetime($link['expires_at'])) ?><?= $link['max_downloads'] ? ' · ' . max(0, (int) $link['max_downloads'] - (int) $link['downloads']) . ' download(s) restante(s)' : '' ?></div>
    <?php if (!$files): ?>
      <div class="text-muted">Nenhum arquivo disponível neste link.</div>
    <?php else: ?>
      <?php if ($folder && count($files) > 1 && $zip): ?>
        <a href="<?= url('/compartilhar/' . $t . '/zip') ?>" class="btn btn-primary btn-sm mb-3"><i class="bi bi-file-earmark-zip"></i> Baixar tudo em ZIP (<?= count($files) ?> arquivos)</a>
      <?php endif; ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($files as $f): ?>
          <li class="list-group-item d-flex align-items-center gap-3 px-0">
            <?php if ($f['thumb_ref']): ?><img src="<?= url('/compartilhar/' . $t . '/miniatura/' . (int) $f['id']) ?>" class="thumb-sm" alt="" width="40" height="40"><?php else: ?><span class="thumb-sm"><i class="bi <?= FileTypes::icon($f['category'], $f['extension']) ?>"></i></span><?php endif; ?>
            <div class="flex-grow-1 min-w-0"><div class="text-truncate fw-semibold"><?= e($f['title'] ?: $f['original_name']) ?></div><div class="small text-muted"><?= e(format_bytes((int) $f['size_bytes'])) ?></div></div>
            <a href="<?= url('/compartilhar/' . $t . '/arquivo/' . (int) $f['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
