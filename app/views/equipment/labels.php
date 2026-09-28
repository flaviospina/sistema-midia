<p class="d-print-none small text-muted">Etiquetas de 62 × 32 mm (cabem 3 por linha em A4). Use papel adesivo; se preferir outro tamanho, ajuste a escala na caixa de impressão.</p>
<div class="labels-sheet">
  <?php foreach ($items as $it): ?>
    <div class="label">
      <div data-qr="<?= e($it['qrUrl']) ?>" data-size="3" class="label-qr"></div>
      <div class="label-text">
        <div class="label-code"><?= e($it['code']) ?></div>
        <div class="label-name"><?= e(truncate($it['name'], 40)) ?></div>
        <div class="label-org">Mídia ADMoema</div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
