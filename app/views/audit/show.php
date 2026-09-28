<?php $pretty = static function (?string $json): string { if ($json === null) return '—'; $d = json_decode($json, true); return $d === null ? $json : (string) json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Registro #<?= (int) $entry['id'] ?></h1>
  <a href="<?= url('/auditoria') ?>" class="btn btn-sm btn-outline-secondary">Voltar</a>
</div>
<div class="card mb-3"><div class="card-body">
  <dl class="row mb-0 small">
    <dt class="col-sm-3">Quando</dt><dd class="col-sm-9"><?= e(format_datetime($entry['created_at'])) ?></dd>
    <dt class="col-sm-3">Quem</dt><dd class="col-sm-9"><?= $entry['user_id'] ? e($entry['user_name'] ?? '') . ' (#' . (int) $entry['user_id'] . ')' : '—' ?></dd>
    <dt class="col-sm-3">Ação</dt><dd class="col-sm-9"><code><?= e($entry['action']) ?></code></dd>
    <dt class="col-sm-3">Entidade</dt><dd class="col-sm-9"><?= e($entry['entity']) ?><?= $entry['entity_id'] !== null ? ' #' . e($entry['entity_id']) : '' ?></dd>
    <dt class="col-sm-3">IP / navegador</dt><dd class="col-sm-9"><?= e($entry['ip']) ?> · <span class="text-muted"><?= e($entry['user_agent']) ?></span></dd>
  </dl>
</div></div>
<div class="row g-3">
  <div class="col-md-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Antes</div><div class="card-body"><pre class="json mb-0"><?= e($pretty($entry['before_data'])) ?></pre></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Depois</div><div class="card-body"><pre class="json mb-0"><?= e($pretty($entry['after_data'])) ?></pre></div></div></div>
</div>
