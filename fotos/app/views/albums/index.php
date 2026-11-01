<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h1 class="h4 mb-0">Álbuns</h1><?php if ($totals): ?><div class="text-muted small"><?= (int) $totals['albums'] ?> pasta(s) · <?= number_format((int) $totals['photos'], 0, ',', '.') ?> fotos · <?= e(format_bytes((int) $totals['bytes'])) ?></div><?php endif; ?></div>
  <?php if (Auth::can('albums.manage')): ?><a href="<?= url('/albuns/novo') ?>" class="btn btn-sm btn-primary"><i class="bi bi-folder-plus"></i> Nova pasta</a><?php endif; ?>
</div>
<form class="d-flex gap-2 flex-wrap mb-3" method="get" action="<?= url('/') ?>">
  <div class="btn-group btn-group-sm" role="group">
    <a href="<?= url('/', $q ? ['q' => $q] : []) ?>" class="btn <?= $year === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
    <?php foreach ($years as $y): ?><a href="<?= url('/', ['ano' => $y] + ($q ? ['q' => $q] : [])) ?>" class="btn <?= (string) $y === $year ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $y ?></a><?php endforeach; ?>
  </div>
  <div class="input-group input-group-sm w-auto ms-auto"><?php if ($year): ?><input type="hidden" name="ano" value="<?= e($year) ?>"><?php endif; ?><input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar pasta"><button class="btn btn-outline-primary"><i class="bi bi-search"></i></button></div>
</form>
<?php if (!$albums): ?>
  <div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-images fs-1 d-block mb-2"></i>Nenhuma pasta de fotos<?= $q ? ' encontrada' : ' ainda' ?>.<?php if (Auth::can('albums.manage')): ?><br><a href="<?= url('/albuns/novo') ?>" class="btn btn-primary btn-sm mt-3">Criar a primeira pasta</a><?php endif; ?></div></div>
<?php else: $month = ''; ?>
  <?php foreach ($albums as $a): $m = substr($a['event_date'], 0, 7); if ($m !== $month): if ($month !== '') echo '</div>'; $month = $m; ?>
    <h2 class="h6 text-muted text-uppercase mt-3 mb-2"><?= e(strftime_pt($a['event_date'])) ?></h2><div class="album-grid">
  <?php endif; ?>
    <a href="<?= url('/albuns/' . (int) $a['id']) ?>" class="album-card<?= $a['hidden'] ? ' album-card--hidden' : '' ?>">
      <div class="album-cover"><?php if ($a['cover_id']): ?><img src="<?= url('/fotos/' . (int) $a['cover_id'] . '/miniatura') ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-camera"></i><?php endif; ?>
        <span class="album-count"><i class="bi bi-image"></i> <?= (int) $a['photos_count'] ?></span><?php if ($a['hidden']): ?><span class="album-hidden" title="Só a equipe vê"><i class="bi bi-eye-slash"></i></span><?php endif; ?></div>
      <div class="album-body"><div class="album-date"><?= e(format_date($a['event_date'], 'd/m')) ?> · <?= e(weekday_pt($a['event_date'])) ?></div><div class="album-name"><?= e($a['name']) ?></div></div>
    </a>
  <?php endforeach; ?></div>
<?php endif; ?>
<?php if ($recent && !$q && !$year): ?>
  <h2 class="h6 text-muted text-uppercase mt-4 mb-2">Últimas fotos</h2>
  <div class="photo-grid photo-grid--sm"><?php foreach ($recent as $p): ?><a href="<?= url('/albuns/' . (int) $p['album_id'], ['foto' => (int) $p['id']]) ?>" class="photo-card"><img src="<?= url('/fotos/' . (int) $p['id'] . '/miniatura') ?>" alt="" loading="lazy"></a><?php endforeach; ?></div>
<?php endif; ?>
