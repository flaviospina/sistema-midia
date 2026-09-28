<h1 class="h4 mb-3">Links de compartilhamento</h1>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
  <thead class="table-light"><tr><th>Conteúdo</th><th>Descrição</th><th>Criado por</th><th>Validade</th><th>Downloads</th><th>Situação</th><th></th></tr></thead>
  <tbody>
  <?php if (!$links): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhum link criado.</td></tr><?php endif; ?>
  <?php foreach ($links as $l): $valid = ShareLink::isValid($l); ?>
    <tr class="<?= $valid ? '' : 'text-muted' ?>">
      <td><?php if ($l['file_id']): ?><a href="<?= url('/arquivos/' . (int) $l['file_id']) ?>"><i class="bi bi-file-earmark"></i> <?= e($l['original_name']) ?></a><?php else: ?><a href="<?= url('/pastas/' . (int) $l['folder_id']) ?>"><i class="bi bi-folder"></i> <?= e($l['folder_name']) ?></a><?php endif; ?></td>
      <td><?= e($l['label'] ?? '') ?></td>
      <td><?= e($l['creator_name'] ?? '—') ?></td>
      <td><?= e(format_datetime($l['expires_at'])) ?></td>
      <td><?= (int) $l['downloads'] ?><?= $l['max_downloads'] ? ' / ' . (int) $l['max_downloads'] : '' ?></td>
      <td><?= $valid ? '<span class="badge text-bg-success">ativo</span>' : '<span class="badge text-bg-secondary">' . ((int) $l['active'] ? 'expirado' : 'desativado') . '</span>' ?></td>
      <td class="text-end text-nowrap">
        <?php if ($valid): ?>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="<?= e(absolute_url('/compartilhar/' . $l['token'])) ?>" title="Copiar link"><i class="bi bi-clipboard"></i></button>
          <form method="post" action="<?= url('/compartilhamentos/' . (int) $l['id'] . '/desativar') ?>" class="d-inline"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger" title="Desativar"><i class="bi bi-x-lg"></i></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
