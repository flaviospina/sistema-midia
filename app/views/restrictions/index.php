<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Restrições de imagem</h1>
  <?php if (Auth::can('restrictions.manage')): ?><a href="<?= url('/restricoes/nova') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Nova</a><?php endif; ?>
</div>
<p class="small text-muted">Pessoas que <strong>não autorizam</strong> aparecer em fotos e transmissões. Confira esta lista antes de publicar fotos; arquivos marcados com restrição ficam visíveis só a administradores. Uso interno da equipe de mídia.</p>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 table-responsive-stack">
  <thead class="table-light"><tr><th></th><th>Pessoa</th><th>Responsável / contato</th><th>Observações</th><th class="text-center">Arquivos</th><th></th></tr></thead>
  <tbody>
  <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhuma restrição cadastrada.</td></tr><?php endif; ?>
  <?php foreach ($items as $r): ?>
    <tr class="<?= $r['active'] ? '' : 'text-muted' ?>">
      <td><?php if ($r['photo_path']): ?><img src="<?= url('/restricoes/' . (int) $r['id'] . '/foto') ?>" class="ref-photo" alt=""><?php else: ?><span class="avatar avatar-initials avatar-40 rounded-circle"><?= e(initials($r['person_name'])) ?></span><?php endif; ?></td>
      <td data-label="Pessoa"><span class="fw-semibold"><?= e($r['person_name']) ?></span><?= $r['is_minor'] ? ' <span class="badge text-bg-secondary">menor</span>' : '' ?><?= !$r['active'] ? ' <span class="badge text-bg-light border">inativa</span>' : '' ?></td>
      <td data-label="Responsável" class="small"><?= e($r['guardian_name'] ?? '') ?><?= $r['contact'] ? '<br>' . e($r['contact']) : '' ?></td>
      <td data-label="Obs." class="small"><?= e(truncate((string) $r['notes'], 80)) ?></td>
      <td data-label="Arquivos" class="text-center"><?= (int) $r['files_count'] ?></td>
      <td class="text-end"><?php if (Auth::can('restrictions.manage')): ?><a href="<?= url('/restricoes/' . (int) $r['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
