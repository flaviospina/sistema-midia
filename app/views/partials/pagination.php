<?php
/** @var array $result  (total, pagina, paginas, por_pagina) */
if (($result['paginas'] ?? 1) <= 1) { return; }
$base = $_GET; unset($base['pagina']);
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$link = static fn(int $p): string => $path . '?' . http_build_query($base + ['pagina' => $p]);
$cur = $result['pagina']; $last = $result['paginas'];
?>
<nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3" aria-label="Paginação">
  <span class="text-muted small"><?= number_format($result['total'], 0, ',', '.') ?> registro(s) · página <?= $cur ?> de <?= $last ?></span>
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item<?= $cur <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $cur <= 1 ? '#' : e($link($cur - 1)) ?>">‹</a></li>
    <?php for ($p = max(1, $cur - 2); $p <= min($last, $cur + 2); $p++): ?>
      <li class="page-item<?= $p === $cur ? ' active' : '' ?>"><a class="page-link" href="<?= e($link($p)) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
    <li class="page-item<?= $cur >= $last ? ' disabled' : '' ?>"><a class="page-link" href="<?= $cur >= $last ? '#' : e($link($cur + 1)) ?>">›</a></li>
  </ul>
</nav>
