<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Lixeira</h1>
  <?php if ($files): ?>
    <form method="post" action="<?= url('/arquivos/lixeira/esvaziar') ?>" data-confirm="Apagar DEFINITIVAMENTE todos os arquivos da lixeira?"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Esvaziar lixeira</button></form>
  <?php endif; ?>
</div>
<p class="small text-muted">Arquivos na lixeira são apagados automaticamente após <?= RETENTION_TRASH_DAYS ?> dias; rejeitados na quarentena, após <?= RETENTION_REJECTED_DAYS ?> dias.</p>

<div class="card mb-3">
  <div class="card-header bg-white fw-semibold">Na lixeira (<?= count($files) ?>)</div>
  <?php if (!$files): ?><div class="card-body text-muted small">Vazia.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
    <thead class="table-light"><tr><th>Arquivo</th><th>Pasta</th><th>Tamanho</th><th>Removido em</th><th></th></tr></thead>
    <tbody><?php foreach ($files as $f): ?>
      <tr>
        <td><a href="<?= url('/arquivos/' . (int) $f['id']) ?>"><?= e($f['original_name']) ?></a></td>
        <td><?= e(Folder::pathLabel($f['folder_id'] ? (int) $f['folder_id'] : null)) ?></td>
        <td><?= e(format_bytes((int) $f['size_bytes'])) ?></td>
        <td><?= e(format_datetime($f['trashed_at'])) ?></td>
        <td class="text-end"><form method="post" action="<?= url('/arquivos/' . (int) $f['id'] . '/restaurar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-success">Restaurar</button></form></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header bg-white fw-semibold">Rejeitados na quarentena (<?= count($rejected) ?>)</div>
  <?php if (!$rejected): ?><div class="card-body text-muted small">Nenhum.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
    <thead class="table-light"><tr><th>Arquivo</th><th>Enviado por</th><th>Motivo</th><th>Rejeitado em</th></tr></thead>
    <tbody><?php foreach ($rejected as $f): ?>
      <tr>
        <td><a href="<?= url('/arquivos/' . (int) $f['id']) ?>"><?= e($f['original_name']) ?></a></td>
        <td><?= e($f['uploader_name'] ?? ($f['guest_name'] ? $f['guest_name'] . ' (convidado)' : '—')) ?></td>
        <td><?= e($f['reject_reason']) ?></td>
        <td><?= e(format_datetime($f['moderated_at'])) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
