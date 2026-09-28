<?php $now = time(); $icsUrl = absolute_url('/calendario/' . $icsToken . '.ics'); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 mb-0">Minha escala</h1>
  <div class="d-flex gap-2">
    <a href="<?= url('/indisponibilidades') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-x"></i> Indisponibilidades</a>
    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#icsBox"><i class="bi bi-calendar-plus"></i> Assinar no calendário</button>
  </div>
</div>
<div class="collapse mb-3" id="icsBox"><div class="card card-body small">
  <p class="mb-2">Copie o endereço abaixo e adicione como calendário por URL. Ele atualiza sozinho quando a escala mudar (o Google leva algumas horas para sincronizar). <strong>Não compartilhe</strong>: o link é pessoal.</p>
  <ul class="mb-2 ps-3">
    <li><strong>Google Agenda (no computador):</strong> abra <a href="https://calendar.google.com" target="_blank" rel="noopener">calendar.google.com</a> → à esquerda, ao lado de "Outras agendas", clique em <strong>+</strong> → <strong>Por URL</strong> → cole o endereço → <strong>Adicionar agenda</strong>. Ela aparece também no app do celular.</li>
    <li><strong>iPhone:</strong> Ajustes → Calendário → Contas → Adicionar conta → Outra → <strong>Adicionar calendário assinado</strong> → cole o endereço.</li>
    <li><strong>Outlook:</strong> Adicionar calendário → Assinar da web → cole o endereço.</li>
  </ul>
  <div class="d-flex gap-2 align-items-center flex-wrap"><input type="text" id="icsUrl" class="form-control form-control-sm share-url flex-grow-1 w-auto" value="<?= e($icsUrl) ?>" readonly><button class="btn btn-sm btn-outline-dark" type="button" data-copy-input="icsUrl"><i class="bi bi-clipboard"></i> Copiar</button>
    <form method="post" action="<?= url('/minha-escala/ics/renovar') ?>" data-confirm="Gerar novo link? O atual deixa de funcionar."><?= Csrf::field() ?><button class="btn btn-sm btn-link text-danger">gerar novo link</button></form></div>
</div></div>

<?php $incoming = array_filter($swaps, static fn($s) => (int) $s['to_user_id'] === Auth::id() && $s['status'] === 'aguardando_membro'); ?>
<?php if ($incoming): ?>
  <div class="card border-warning mb-3"><div class="card-header bg-white fw-semibold">Pedidos de troca para você</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($incoming as $s): ?>
        <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
          <span class="flex-grow-1"><strong><?= e($s['from_name']) ?></strong> pede que você assuma <strong><?= e($s['function_name']) ?></strong> em <?= e($s['event_title']) ?> (<?= e(format_datetime($s['starts_at'])) ?>)<?= $s['reason'] ? '<br><span class="small text-muted">' . e($s['reason']) . '</span>' : '' ?></span>
          <form method="post" action="<?= url('/trocas/' . (int) $s['id'] . '/aceitar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-success">Aceitar</button></form>
          <form method="post" action="<?= url('/trocas/' . (int) $s['id'] . '/recusar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-secondary">Recusar</button></form>
        </li>
      <?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<?php if (!$assignments): ?><div class="card"><div class="card-body text-center text-muted py-4">Você não está escalado(a) nos próximos meses.</div></div><?php endif; ?>
<?php foreach ($assignments as $a): $aid = (int) $a['id']; $future = strtotime($a['starts_at']) > $now; $cancelled = $a['event_status'] === 'cancelado'; $swap = $openSwaps[$aid] ?? null; ?>
  <div class="card mb-2<?= !$future ? ' opacity-75' : '' ?><?= $cancelled ? ' border-secondary' : ($a['status'] === 'pendente' && $future ? ' border-warning' : '') ?>">
    <div class="card-body py-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="text-center minw-56"><div class="fw-bold fs-5 lh-1"><?= e(date('d', strtotime($a['starts_at']))) ?></div><div class="small text-muted"><?= e(['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'][(int) date('n', strtotime($a['starts_at'])) - 1]) ?> · <?= e(substr($a['starts_at'], 11, 5)) ?></div></div>
        <div class="flex-grow-1">
          <a href="<?= url('/eventos/' . (int) $a['event_id']) ?>" class="fw-semibold text-decoration-none"><?= e($a['event_title']) ?></a>
          <?php if ($cancelled): ?><span class="badge text-bg-secondary">evento cancelado</span><?php endif; ?>
          <div class="small"><span class="badge text-bg-light border"><?= e($a['function_name']) ?></span>
            <span class="badge <?= $a['status'] === 'confirmado' ? 'text-bg-success' : ($a['status'] === 'recusado' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= e(Assignment::STATUSES[$a['status']]) ?></span>
            <?php if ($a['location']): ?><span class="text-muted"><i class="bi bi-geo-alt"></i> <?= e($a['location']) ?></span><?php endif; ?>
            <?php if ($swap): ?><span class="badge text-bg-info">troca: <?= e(SwapRequest::STATUSES[$swap['status']]) ?></span><?php endif; ?></div>
        </div>
        <?php if ($future && !$cancelled): ?>
          <div class="d-flex gap-1 flex-wrap">
            <?php if ($a['status'] !== 'confirmado'): ?><form method="post" action="<?= url('/escalas/' . $aid . '/responder') ?>"><?= Csrf::field() ?><input type="hidden" name="status" value="confirmado"><button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Confirmar</button></form><?php endif; ?>
            <?php if ($a['status'] !== 'recusado'): ?><button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#dec<?= $aid ?>">Recusar</button><?php endif; ?>
            <?php if ($a['status'] !== 'recusado' && !$swap && !empty($swapCandidates[$aid])): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#swap<?= $aid ?>"><i class="bi bi-arrow-left-right"></i> Trocar</button><?php endif; ?>
            <?php if ($swap): ?><form method="post" action="<?= url('/trocas/' . (int) $swap['id'] . '/cancelar') ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-link text-danger">cancelar troca</button></form><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($future && !$cancelled): ?>
        <form method="post" action="<?= url('/escalas/' . $aid . '/responder') ?>" class="collapse mt-2" id="dec<?= $aid ?>"><?= Csrf::field() ?><input type="hidden" name="status" value="recusado">
          <div class="input-group input-group-sm"><input type="text" name="note" class="form-control" placeholder="Motivo da recusa (obrigatório)" maxlength="300" required><button class="btn btn-danger">Confirmar recusa</button></div></form>
        <?php if (!empty($swapCandidates[$aid])): ?>
        <form method="post" action="<?= url('/escalas/' . $aid . '/troca') ?>" class="collapse mt-2" id="swap<?= $aid ?>"><?= Csrf::field() ?>
          <div class="row g-1">
            <div class="col-md-5"><select name="to_user_id" class="form-select form-select-sm" required><option value="">Trocar com…</option><?php foreach ($swapCandidates[$aid] as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-5"><input type="text" name="reason" class="form-control form-control-sm" placeholder="Motivo (opcional)" maxlength="300"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Pedir troca</button></div>
          </div><div class="form-text">O colega precisa aceitar e o coordenador aprovar.</div></form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php $history = array_filter($swaps, static fn($s) => !in_array($s['status'], ['aguardando_membro'], true) || (int) $s['from_user_id'] === Auth::id()); ?>
<?php if ($history): ?>
  <h2 class="h6 text-muted mt-4">Trocas</h2>
  <ul class="list-group small">
    <?php foreach ($history as $s): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($s['from_name']) ?> → <?= e($s['to_name']) ?> · <?= e($s['function_name']) ?> · <?= e($s['event_title']) ?> (<?= e(format_date($s['starts_at'])) ?>)</span><span class="text-muted"><?= e(SwapRequest::STATUSES[$s['status']]) ?></span></li><?php endforeach; ?>
  </ul>
<?php endif; ?>
