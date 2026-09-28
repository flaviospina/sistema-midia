<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-0">Funções</h1>
    <div class="text-muted small"><?= e($u['name']) ?></div>
  </div>
  <a href="<?= url('/usuarios/' . (int) $u['id']) ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
</div>

<form method="post" action="<?= url('/usuarios/' . (int) $u['id'] . '/funcoes') ?>">
  <?= Csrf::field() ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>Função</th><th>Nível</th><th>Treinado em</th><?php if (Auth::is('admin')): ?><th>Coordena a área</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($functions as $f): $fid = (int) $f['id']; $cur = $current[$fid] ?? null; ?>
          <tr data-func-row>
            <td>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="func[<?= $fid ?>][on]" value="1" id="f<?= $fid ?>"<?= checked($cur !== null) ?>>
                <label class="form-check-label fw-semibold" for="f<?= $fid ?>"><?= e($f['name']) ?></label>
              </div>
              <?php if ($f['description']): ?><div class="small text-muted ps-4"><?= e($f['description']) ?></div><?php endif; ?>
              <?= field_error('func_' . $fid) ?>
            </td>
            <td>
              <select class="form-select form-select-sm" name="func[<?= $fid ?>][level]" aria-label="Nível">
                <?php foreach (MediaFunction::LEVELS as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= selected($k, $cur['level'] ?? 'aprendiz') ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="date" class="form-control form-control-sm" name="func[<?= $fid ?>][trained_at]" value="<?= e($cur['trained_at'] ?? '') ?>" aria-label="Treinado em"></td>
            <?php if (Auth::is('admin')): ?>
              <td><div class="form-check"><input class="form-check-input" type="checkbox" name="func[<?= $fid ?>][coord]" value="1"<?= checked(!empty($cur['is_coordinator'])) ?> aria-label="Coordena"></div></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <span class="small text-muted">Só quem está <strong>apto</strong> ou é <strong>referência</strong> entra na sugestão automática de escala (Fase 3).</span>
      <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </div>
</form>
