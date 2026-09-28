<h1 class="h4 mb-3">Integrações · n8n / WhatsApp</h1>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold mb-2">Configuração (.env)</div>
    <ul class="list-unstyled small mb-0">
      <li><i class="bi <?= $enabled ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?>"></i> NOTIFY_ENABLED=<?= $enabled ? '1' : '0' ?></li>
      <li><i class="bi <?= $configured ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?>"></i> Webhook de saída <?= $configured ? '<span class="text-muted">' . e(preg_replace('#^(https?://[^/]+).*#', '$1/…', $url)) . '</span>' : '(N8N_WEBHOOK_URL / N8N_WEBHOOK_SECRET)' ?></li>
      <li><i class="bi <?= $inbound ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?>"></i> Segredo de entrada (N8N_INBOUND_SECRET)</li>
      <li><i class="bi <?= $curl ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?>"></i> Extensão cURL</li>
      <li><i class="bi bi-clock"></i> Rotinas diárias às <?= NOTIFY_DAILY_HOUR ?>h · última: <?= e($lastDaily ? format_date($lastDaily) : 'nunca') ?></li>
    </ul>
    <div class="small text-muted mt-2">Endpoints para o n8n chamar: <code><?= e(BASE_URL . url('/api/n8n/ping')) ?></code> (GET) e <code><?= e(BASE_URL . url('/api/n8n/entrada')) ?></code> (POST), com header <code>X-Webhook-Secret</code>.</div>
  </div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold mb-2">Fila</div>
    <div class="d-flex gap-3 mb-2">
      <div><div class="stat-value fs-4"><?= $stats['pendente'] ?></div><div class="small text-muted">pendentes</div></div>
      <div><div class="stat-value fs-4 text-success"><?= $stats['enviado'] ?></div><div class="small text-muted">enviados</div></div>
      <div><div class="stat-value fs-4 <?= $stats['falhou'] ? 'text-danger' : '' ?>"><?= $stats['falhou'] ?></div><div class="small text-muted">falharam</div></div>
    </div>
    <div class="small text-muted mb-2">Último envio: <?= e($stats['ultimo_envio'] ? format_datetime($stats['ultimo_envio']) : '—') ?></div>
    <div class="d-flex gap-2">
      <form method="post" action="<?= url('/integracoes/teste') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-primary"<?= $configured ? '' : ' disabled' ?>><i class="bi bi-send"></i> Enviar teste para mim</button></form>
      <form method="post" action="<?= url('/integracoes/processar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Processar fila</button></form>
    </div>
  </div></div></div>
  <div class="col-md-4"><form method="post" action="<?= url('/integracoes/eventos') ?>" class="card h-100"><?= Csrf::field() ?>
    <div class="card-body"><div class="fw-semibold mb-2">Avisos ligados</div>
      <?php foreach ($events as $k => [$label, $default]): ?>
        <div class="form-check form-switch small"><input class="form-check-input" type="checkbox" role="switch" name="events[]" value="<?= e($k) ?>" id="ev_<?= e(str_replace('.', '_', $k)) ?>"<?= checked(Setting::eventEnabled($k)) ?>><label class="form-check-label" for="ev_<?= e(str_replace('.', '_', $k)) ?>"><?= e($label) ?></label></div>
      <?php endforeach; ?>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-sm btn-outline-primary w-100">Salvar</button></div>
  </form></div>
</div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Últimos avisos</div>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
    <thead class="table-light"><tr><th>#</th><th>Quando</th><th>Evento</th><th>Para</th><th>Mensagem</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php if (!$items): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhum aviso ainda.</td></tr><?php endif; ?>
    <?php foreach ($items as $n): $rec = json_decode((string) $n['recipients'], true) ?: []; ?>
      <tr>
        <td><?= (int) $n['id'] ?></td>
        <td class="text-nowrap"><?= e(format_datetime($n['created_at'])) ?></td>
        <td><code><?= e($n['event']) ?></code></td>
        <td><?= e(implode(', ', array_map(static fn($r) => $r['name'], array_slice($rec, 0, 3)))) ?><?= count($rec) > 3 ? ' +' . (count($rec) - 3) : '' ?></td>
        <td class="text-muted"><?= e(truncate(str_replace("\n", ' ', $n['message']), 90)) ?></td>
        <td><span class="badge <?= $n['status'] === 'enviado' ? 'text-bg-success' : ($n['status'] === 'falhou' ? 'text-bg-danger' : ($n['status'] === 'cancelado' ? 'text-bg-secondary' : 'text-bg-warning')) ?>"><?= e(Notification::STATUSES[$n['status']]) ?></span>
          <?php if ($n['last_error']): ?><div class="text-danger" title="<?= e($n['last_error']) ?>"><?= e(truncate($n['last_error'], 50)) ?></div><?php endif; ?><?php if ($n['attempts']): ?><div class="text-muted"><?= (int) $n['attempts'] ?> tentativa(s)</div><?php endif; ?></td>
        <td class="text-end text-nowrap">
          <?php if (in_array($n['status'], ['falhou', 'cancelado', 'enviado'], true)): ?><form method="post" action="<?= url('/integracoes/' . (int) $n['id'] . '/reenviar') ?>" class="d-inline"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary" title="Reenviar"><i class="bi bi-arrow-repeat"></i></button></form><?php endif; ?>
          <?php if ($n['status'] === 'pendente'): ?><form method="post" action="<?= url('/integracoes/' . (int) $n['id'] . '/cancelar') ?>" class="d-inline"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary" title="Cancelar"><i class="bi bi-x-lg"></i></button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card"><div class="card-header bg-white fw-semibold">Respostas recebidas do WhatsApp</div>
  <div class="table-responsive"><table class="table table-sm mb-0 small">
    <thead class="table-light"><tr><th>Quando</th><th>De</th><th>Texto</th><th>Ação</th><th>Resposta</th></tr></thead>
    <tbody>
    <?php if (!$inboundLog): ?><tr><td colspan="5" class="text-center text-muted py-3">Nenhuma mensagem recebida ainda.</td></tr><?php endif; ?>
    <?php foreach ($inboundLog as $m): ?>
      <tr><td class="text-nowrap"><?= e(format_datetime($m['created_at'])) ?></td><td><?= e($m['user_name'] ?? format_phone($m['phone'])) ?></td><td><?= e(truncate($m['text'], 60)) ?></td><td><code><?= e($m['action'] ?? '') ?></code></td><td class="text-muted"><?= e(truncate((string) $m['reply'], 80)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
