<?php /** @var array $file  @var bool $selectable */ $selectable = $selectable ?? true; $fid = (int) $file['id']; ?>
<div class="file-card">
  <?php if ($selectable && $file['status'] === 'aprovado'): ?>
    <input type="checkbox" class="form-check-input file-select" value="<?= $fid ?>" data-select aria-label="Selecionar">
  <?php endif; ?>
  <?php if ($file['status'] !== 'aprovado'): ?>
    <span class="badge file-badge <?= $file['status'] === 'quarentena' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= e(MediaFile::STATUSES[$file['status']]) ?></span>
  <?php elseif ((int) $file['has_restriction'] === 1): ?>
    <span class="badge file-badge text-bg-danger" title="Contém pessoa com restrição de imagem"><i class="bi bi-eye-slash"></i></span>
  <?php endif; ?>
  <a href="<?= url('/arquivos/' . $fid) ?>" class="file-thumb">
    <?php if ($file['thumb_ref']): ?>
      <img src="<?= url('/arquivos/' . $fid . '/miniatura') ?>" alt="" loading="lazy">
    <?php else: ?>
      <i class="bi <?= FileTypes::icon($file['category'], $file['extension']) ?>"></i>
    <?php endif; ?>
  </a>
  <?php if ($file['duration_seconds']): ?><span class="file-duration"><?= e(format_duration((int) $file['duration_seconds'])) ?></span><?php endif; ?>
  <div class="file-body">
    <a href="<?= url('/arquivos/' . $fid) ?>" class="file-name" title="<?= e($file['original_name']) ?>"><?= e($file['title'] ?: $file['original_name']) ?></a>
    <div class="text-muted"><?= e(format_bytes((int) $file['size_bytes'])) ?> · <?= e(format_date($file['created_at'])) ?></div>
  </div>
</div>
