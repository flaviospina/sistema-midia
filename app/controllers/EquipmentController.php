<?php
// app/controllers/EquipmentController.php — patrimônio: inventário, QR Code, empréstimo/devolução, manutenção
declare(strict_types=1);

final class EquipmentController
{
    public function index(): never
    {
        $f = ['q' => query('q'), 'category' => isset(Equipment::CATEGORIES[query('categoria')]) ? query('categoria') : '', 'status' => isset(Equipment::STATUSES[query('status')]) ? query('status') : '', 'all' => query('todos') === '1'];
        view('equipment/index', [
            'title'   => 'Patrimônio',
            'filters' => $f,
            'result'  => Equipment::search($f, current_page()),
            'summary' => Equipment::summary(),
            'overdue' => Auth::can('equipment.manage') ? Equipment::overdueLoans() : [],
            'mine'    => Equipment::myLoans((int) Auth::id()),
        ]);
    }

    public function create(): never
    {
        view('equipment/form', ['title' => 'Novo item', 'q' => null, 'nextCode' => Equipment::nextCode()]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = Equipment::create($d);
        $this->handlePhoto($id, null);
        Logger::audit('patrimonio_criado', 'equipment', $id, null, $d);
        flash('success', 'Item cadastrado. Imprima a etiqueta com o QR Code.');
        redirect('/patrimonio/' . $id);
    }

    public function show(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        view('equipment/show', [
            'title'       => $q['code'] . ' · ' . $q['name'],
            'q'           => $q,
            'loans'       => Equipment::loans($id),
            'openLoan'    => Equipment::openLoan($id),
            'maintenance' => Equipment::maintenance($id),
            'incidents'   => Database::all('SELECT id, title, severity, status, created_at FROM incidents WHERE equipment_id = :e ORDER BY id DESC LIMIT 10', ['e' => $id]),
            'team'        => Database::all("SELECT u.id, u.name FROM users u JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app WHERE u.status = 'ativo' AND r.role IN ('admin','coordenador','membro_midia') ORDER BY u.name", ['app' => APP_CODE]),
            'qrUrl'       => absolute_url('/patrimonio/q/' . $q['qr_token']),
        ]);
    }

    /** Acesso pelo QR Code impresso na etiqueta. */
    public function byToken(string $token): never
    {
        $q = Equipment::findByToken($token) ?? abort(404, 'Etiqueta não reconhecida.');
        redirect('/patrimonio/' . (int) $q['id']);
    }

    public function label(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        view('equipment/label', ['title' => 'Etiqueta ' . $q['code'], 'q' => $q, 'qrUrl' => absolute_url('/patrimonio/q/' . $q['qr_token'])], 'layout_print');
    }

    public function labels(): never
    {
        $ids = array_map('intval', array_filter((array) ($_POST['ids'] ?? []), 'is_numeric'));
        $items = [];
        foreach (array_unique($ids) as $id) {
            if ($q = Equipment::find($id)) {
                $q['qrUrl'] = absolute_url('/patrimonio/q/' . $q['qr_token']);
                $items[] = $q;
            }
        }
        if (!$items) {
            flash('warning', 'Selecione ao menos um item.');
            redirect('/patrimonio');
        }
        view('equipment/labels', ['title' => 'Etiquetas', 'items' => $items], 'layout_print');
    }

    public function edit(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        view('equipment/form', ['title' => 'Editar: ' . $q['code'], 'q' => $q, 'nextCode' => $q['code']]);
    }

    public function update(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        $d = $this->validate($q);
        if ($q['status'] === 'emprestado' && $d['status'] !== 'emprestado') {
            $d['status'] = 'emprestado'; // devolução é pelo botão próprio
        }
        Equipment::update($id, $d);
        $this->handlePhoto($id, $q['photo_path']);
        Logger::audit('patrimonio_alterado', 'equipment', $id, array_intersect_key($q, $d), $d);
        flash('success', 'Item atualizado.');
        redirect('/patrimonio/' . $id);
    }

    public function loan(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        if ($q['status'] !== 'disponivel') {
            flash('warning', 'Este item não está disponível (' . Equipment::STATUSES[$q['status']] . ').');
            redirect('/patrimonio/' . $id);
        }
        $uid = ctype_digit(input('user_id')) ? (int) input('user_id') : (int) Auth::id();
        if ($uid !== Auth::id() && !Auth::can('equipment.manage')) {
            abort(403);
        }
        $u = User::find($uid);
        if (!$u || $u['status'] !== 'ativo') {
            flash('danger', 'Pessoa inválida.');
            redirect('/patrimonio/' . $id);
        }
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('due_on')) ? input('due_on') : null;
        $lid = Equipment::loan($id, $uid, mb_substr(input('purpose'), 0, 200) ?: null, $due);
        Logger::audit('patrimonio_emprestado', 'equipment_loans', $lid, null, ['equipment_id' => $id, 'user_id' => $uid, 'due_on' => $due]);
        flash('success', $q['code'] . ' emprestado para ' . $u['name'] . ($due ? ' até ' . format_date($due) : '') . '.');
        redirect('/patrimonio/' . $id);
    }

    public function returnLoan(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        $loan = Equipment::openLoan($id);
        if (!$loan) {
            flash('info', 'Este item não está emprestado.');
            redirect('/patrimonio/' . $id);
        }
        if ((int) $loan['user_id'] !== Auth::id() && !Auth::can('equipment.manage')) {
            abort(403);
        }
        $cond = input('condition') === 'danificado' ? 'danificado' : 'ok';
        Equipment::returnLoan((int) $loan['id'], $cond, mb_substr(input('notes'), 0, 500) ?: null);
        Logger::audit('patrimonio_devolvido', 'equipment_loans', $loan['id'], null, ['condition' => $cond]);
        flash('success', 'Devolução registrada' . ($cond === 'danificado' ? ' — item marcado como em manutenção.' : '.'));
        redirect('/patrimonio/' . $id);
    }

    public function openMaintenance(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        $v = (new Validator($_POST))->required('description', 'a descrição do problema')->max('description', 500, 'Descrição')->max('provider', 120, 'Fornecedor')->in('kind', ['preventiva', 'corretiva'], 'Tipo');
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/patrimonio/' . $id);
        }
        $mid = Equipment::openMaintenance($id, ['kind' => input('kind') ?: 'corretiva', 'description' => input('description'), 'provider' => input('provider') ?: null, 'opened_on' => date('Y-m-d'), 'cost_cents' => self::cents(input('cost'))]);
        Logger::audit('manutencao_aberta', 'equipment_maintenance', $mid, null, ['equipment_id' => $id]);
        flash('success', 'Manutenção aberta; item marcado como em manutenção.');
        redirect('/patrimonio/' . $id);
    }

    public function closeMaintenance(int $id, int $maintenanceId): never
    {
        Equipment::find($id) ?? abort(404);
        Equipment::closeMaintenance($maintenanceId, mb_substr(input('result'), 0, 500) ?: null, self::cents(input('cost')), input('outcome') !== 'baixar');
        Logger::audit('manutencao_fechada', 'equipment_maintenance', $maintenanceId, null, ['outcome' => input('outcome')]);
        flash('success', input('outcome') === 'baixar' ? 'Manutenção encerrada; item baixado do inventário.' : 'Manutenção encerrada; item disponível.');
        redirect('/patrimonio/' . $id);
    }

    public function photo(int $id): never
    {
        $q = Equipment::find($id) ?? abort(404);
        $path = $q['photo_path'] && preg_match('/^[a-f0-9]{32}\.jpg$/', $q['photo_path']) ? STORAGE_PATH . '/equipment/' . $q['photo_path'] : null;
        if (!$path || !is_file($path)) {
            abort(404);
        }
        Delivery::send($path, 'image/jpeg', 'foto.jpg', true, 3600);
    }

    private static function cents(string $v): ?int
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $n = (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', str_replace('.', '', $v)) ?? '');
        return (int) round($n * 100);
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('code', 'o código')->max('code', 20, 'Código')->required('name', 'o nome')->max('name', 120, 'Nome')
            ->in('category', array_keys(Equipment::CATEGORIES), 'Categoria')->in('status', array_keys(Equipment::STATUSES), 'Situação')
            ->max('brand', 80, 'Marca')->max('model', 80, 'Modelo')->max('serial_number', 80, 'Nº de série')->max('location', 120, 'Local')->max('notes', 3000, 'Observações')
            ->date('acquired_on', 'Data de aquisição');
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,19}$/i', input('code'))) {
            $v->add('code', 'Use letras, números e hífen (ex.: MID-0001).');
        } elseif (Equipment::codeExists(strtoupper(input('code')), $existing['id'] ?? null)) {
            $v->add('code', 'Já existe um item com este código.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/patrimonio/' . $existing['id'] . '/editar' : '/patrimonio/novo');
        }
        return [
            'code' => strtoupper(input('code')), 'name' => input('name'), 'category' => input('category') ?: 'outro', 'brand' => input('brand') ?: null,
            'model' => input('model') ?: null, 'serial_number' => input('serial_number') ?: null, 'acquired_on' => input('acquired_on') ?: null,
            'value_cents' => self::cents(input('value')), 'location' => input('location') ?: null,
            'status' => $existing ? (input('status') ?: $existing['status']) : 'disponivel', 'notes' => input('notes') ?: null,
        ];
    }

    private function handlePhoto(int $id, ?string $old): void
    {
        try {
            if (input('remove_photo') === '1' && $old) {
                @unlink(STORAGE_PATH . '/equipment/' . $old);
                Equipment::setPhoto($id, null);
                return;
            }
            $new = Photo::store('photo');
            if ($new !== null) {
                $dir = STORAGE_PATH . '/equipment';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0750, true);
                }
                @rename(STORAGE_PATH . '/photos/' . $new, $dir . '/' . $new);
                if ($old) {
                    @unlink($dir . '/' . $old);
                }
                Equipment::setPhoto($id, $new);
            }
        } catch (InvalidArgumentException $e) {
            flash('warning', 'Foto não gravada: ' . $e->getMessage());
        }
    }
}
