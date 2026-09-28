<?php /** @var array $file */ $fid = (int) $file['id']; ?>
<tr>
  <td class="text-center"><?php if ($file['status'] === 'aprovado'): ?><input type="checkbox" class="form-check-input" value="<?= $fid ?>" data-select aria-label="Selecionar"><?php endif; ?></td>
  <td>
    <a href="<?= url('/arquivos/' . $fid) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-body">
      <?php if ($file['thumb_ref']): ?><img src="<?= url('/arquivos/' . $fid . '/miniatura') ?>" class="thumb-sm" alt="" loading="lazy">
      <?php else: ?><span class="thumb-sm"><i class="bi <?= FileTypes::icon($file['category'], $file['extension']) ?>"></i></span><?php endif; ?>
      <span class="min-w-0">
        <span class="d-block fw-semibold text-truncate"><?= e($file['title'] ?: $file['original_name']) ?></span>
        <?php if ($file['title']): ?><span class="d-block text-muted small text-truncate"><?= e($file['original_name']) ?></span><?php endif; ?>
      </span>
    </a>
  </td>
  <td><?= e(FileTypes::CATEGORIES[$file['category']] ?? $file['category']) ?></td>
  <td class="text-nowrap"><?= e(format_bytes((int) $file['size_bytes'])) ?></td>
  <td class="text-nowrap"><?= e(format_datetime($file['created_at'])) ?></td>
  <td><?= e($file['uploader_name'] ?? ($file['guest_name'] ? $file['guest_name'] . ' (convidado)' : '—')) ?></td>
  <td>
    <?php if ($file['status'] !== 'aprovado'): ?><span class="badge text-bg-warning"><?= e(MediaFile::STATUSES[$file['status']]) ?></span><?php endif; ?>
    <?php if ((int) $file['has_restriction'] === 1): ?><span class="badge text-bg-danger"><i class="bi bi-eye-slash"></i> restrição</span><?php endif; ?>
    <?php foreach ($file['tags'] ?? [] as $t): ?><span class="badge text-bg-light border"><?= e($t['name']) ?></span> <?php endforeach; ?>
  </td>
</tr>
