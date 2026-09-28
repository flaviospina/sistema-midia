<?php
// app/controllers/UserController.php — usuários, equipe de mídia, funções e aprovação
declare(strict_types=1);

final class UserController
{
    public function index(): never
    {
        $filters = [
            'q'             => query('q'),
            'role'          => query('perfil'),
            'status'        => query('status'),
            'member_status' => query('situacao'),
            'function_id'   => query('funcao'),
        ];
        view('users/index', [
            'title'     => 'Pessoas',
            'filters'   => $filters,
            'result'    => User::search($filters, current_page()),
            'functions' => MediaFunction::all(true),
            'pending'   => Auth::can('users.approve') ? User::countPending() : 0,
        ]);
    }

    public function pending(): never
    {
        view('users/pending', ['title' => 'Cadastros pendentes', 'users' => User::pending()]);
    }

    public function approve(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] !== 'pendente') {
            abort(404);
        }
        $role = input('role', 'membro_igreja');
        // Coordenador só aprova como membro da igreja; perfis maiores são do admin
        if (!Auth::is('admin')) {
            $role = 'membro_igreja';
        }
        if (!isset(Auth::ROLES[$role])) {
            abort(422, 'Perfil inválido.');
        }
        Database::transaction(static function () use ($id, $role, $user): void {
            User::setStatus($id, 'ativo');
            User::update($id, ['name' => $user['name'], 'email' => $user['email'], 'whatsapp' => $user['whatsapp']], $role);
        });
        Logger::audit('cadastro_aprovado', 'users', $id, User::snapshot($user), ['status' => 'ativo', 'role' => $role]);
        flash('success', 'Cadastro de ' . $user['name'] . ' aprovado.');
        redirect('/usuarios/pendentes');
    }

    public function reject(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] !== 'pendente') {
            abort(404);
        }
        // Cadastro recusado não deve guardar dados pessoais
        User::anonymize($id);
        Logger::audit('cadastro_recusado', 'users', $id, User::snapshot($user), null);
        flash('info', 'Cadastro recusado e dados removidos.');
        redirect('/usuarios/pendentes');
    }

    public function show(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] === 'anonimizado') {
            abort(404);
        }
        view('users/show', [
            'title'      => $user['name'],
            'u'          => $user,
            'functions'  => MediaFunction::forMember($id),
            'ministries' => User::ministries($id),
            'consents'   => Auth::can('privacy.manage') ? Consent::forUser($id) : [],
        ]);
    }

    public function create(): never
    {
        view('users/form', [
            'title'      => 'Nova pessoa',
            'u'          => null,
            'ministries' => Ministry::all(true),
            'userMin'    => [],
        ]);
    }

    public function store(): never
    {
        $data = $this->validate(null);
        $temp = temp_password();
        $id = Database::transaction(static function () use ($data, $temp): int {
            $id = User::create($data, password_hash($temp, PASSWORD_DEFAULT), $data['role'], true);
            User::syncMinistries($id, $data['ministries'], $data['leader_of']);
            return $id;
        });
        $this->handlePhoto($id, null);
        Logger::audit('usuario_criado', 'users', $id, null, User::snapshot(User::find($id)));
        $_SESSION['_temp_password'] = ['name' => $data['name'], 'email' => $data['email'], 'password' => $temp];
        flash('success', 'Cadastro criado.');
        redirect('/usuarios/' . $id);
    }

    public function edit(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] === 'anonimizado') {
            abort(404);
        }
        view('users/form', [
            'title'      => 'Editar: ' . $user['name'],
            'u'          => $user,
            'ministries' => Ministry::all(true),
            'userMin'    => User::ministries($id),
        ]);
    }

    public function update(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] === 'anonimizado') {
            abort(404);
        }
        $data = $this->validate($user);

        if ($user['role'] === 'admin' && $data['role'] !== 'admin' && User::countAdmins($id) === 0) {
            with_errors(['role' => 'Este é o único administrador ativo. Defina outro antes de mudar o perfil.'], $_POST);
            redirect('/usuarios/' . $id . '/editar');
        }

        Database::transaction(static function () use ($id, $data): void {
            User::update($id, $data, $data['role']);
            User::syncMinistries($id, $data['ministries'], $data['leader_of']);
        });
        if ($data['role'] !== $user['role'] && $id !== Auth::id()) {
            User::bumpSession($id); // perfil mudou: derruba sessões abertas
        }
        $this->handlePhoto($id, $user['photo_path']);
        if ($id === Auth::id()) {
            Auth::refresh();
        }
        Logger::audit('usuario_alterado', 'users', $id, User::snapshot($user), User::snapshot(User::find($id)));
        flash('success', 'Dados atualizados.');
        redirect('/usuarios/' . $id);
    }

    public function functionsForm(int $id): never
    {
        $user = User::find($id);
        if (!$user || !in_array($user['role'], Auth::MEDIA_ROLES, true)) {
            abort(404);
        }
        view('users/functions', [
            'title'     => 'Funções: ' . $user['name'],
            'u'         => $user,
            'functions' => MediaFunction::all(true),
            'current'   => MediaFunction::forMember($id),
        ]);
    }

    public function saveFunctions(int $id): never
    {
        $user = User::find($id);
        if (!$user || !in_array($user['role'], Auth::MEDIA_ROLES, true)) {
            abort(404);
        }
        $before = MediaFunction::forMember($id);
        $items = [];
        $errors = [];
        foreach ($_POST['func'] ?? [] as $fid => $row) {
            if (empty($row['on'])) {
                continue;
            }
            $fid = (int) $fid;
            $level = $row['level'] ?? 'aprendiz';
            $trained = trim((string) ($row['trained_at'] ?? ''));
            if (!isset(MediaFunction::LEVELS[$level])) {
                $errors["func_{$fid}"] = 'Nível inválido.';
            }
            if ($trained !== '') {
                $d = DateTime::createFromFormat('!Y-m-d', $trained);
                if (!$d || $d->format('Y-m-d') !== $trained) {
                    $errors["func_{$fid}"] = 'Data de treinamento inválida.';
                }
            }
            $items[$fid] = [
                'level'          => $level,
                'trained_at'     => $trained,
                'is_coordinator' => Auth::is('admin') ? !empty($row['coord']) : !empty($before[$fid]['is_coordinator']),
            ];
        }
        if ($errors) {
            with_errors($errors);
            redirect('/usuarios/' . $id . '/funcoes');
        }
        MediaFunction::syncMember($id, $items);
        Logger::audit('funcoes_alteradas', 'member_functions', $id, array_values($before), array_values(MediaFunction::forMember($id)));
        flash('success', 'Funções atualizadas.');
        redirect('/usuarios/' . $id);
    }

    public function resetPassword(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] !== 'ativo') {
            abort(404);
        }
        $temp = temp_password();
        User::setPassword($id, password_hash($temp, PASSWORD_DEFAULT), true);
        Logger::audit('senha_redefinida_admin', 'users', $id, null, ['por' => Auth::user()['name'], 'temporaria' => true, 'troca_obrigatoria' => true]);
        $_SESSION['_temp_password'] = ['name' => $user['name'], 'email' => $user['email'], 'password' => $temp];
        flash('success', 'Senha de ' . $user['name'] . ' redefinida com sucesso. A senha temporária abaixo é exibida só desta vez; a pessoa deverá trocá-la no primeiro acesso.');
        redirect_back('/usuarios/' . $id);
    }

    public function toggleStatus(int $id): never
    {
        $user = User::find($id);
        if (!$user || in_array($user['status'], ['anonimizado', 'pendente'], true)) {
            abort(404);
        }
        if ($id === Auth::id()) {
            flash('warning', 'Você não pode desativar o próprio acesso.');
            redirect('/usuarios/' . $id);
        }
        $new = $user['status'] === 'ativo' ? 'inativo' : 'ativo';
        if ($new === 'inativo' && $user['role'] === 'admin' && User::countAdmins($id) === 0) {
            flash('warning', 'Este é o único administrador ativo.');
            redirect('/usuarios/' . $id);
        }
        User::setStatus($id, $new);
        Logger::audit($new === 'ativo' ? 'usuario_ativado' : 'usuario_desativado', 'users', $id, ['status' => $user['status']], ['status' => $new]);
        flash('success', $new === 'ativo' ? 'Acesso reativado.' : 'Acesso desativado.');
        redirect('/usuarios/' . $id);
    }

    public function anonymize(int $id): never
    {
        $user = User::find($id);
        if (!$user || $user['status'] === 'anonimizado') {
            abort(404);
        }
        if ($id === Auth::id()) {
            flash('warning', 'Você não pode remover a própria conta por aqui.');
            redirect('/usuarios/' . $id);
        }
        if ($user['role'] === 'admin' && User::countAdmins($id) === 0) {
            flash('warning', 'Este é o único administrador ativo.');
            redirect('/usuarios/' . $id);
        }
        if (input('confirm') !== $user['email']) {
            flash('danger', 'Para confirmar, digite o e-mail exato da pessoa.');
            redirect('/usuarios/' . $id);
        }
        User::anonymize($id);
        Logger::audit('usuario_anonimizado', 'users', $id, ['name' => $user['name'], 'email' => $user['email']], null);
        flash('success', 'Dados pessoais removidos. O registro histórico foi mantido de forma anônima.');
        redirect('/usuarios');
    }

    public function photo(int $id): never
    {
        // A própria pessoa, equipe de mídia e pastor podem ver fotos
        if ($id !== Auth::id() && !Auth::isMedia() && !Auth::is('pastor')) {
            abort(403);
        }
        $user = User::find($id);
        Photo::serve($user['photo_path'] ?? null);
    }

    // ------------------------------------------------------------------

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('name', 'o nome completo')->max('name', 150, 'Nome')
            ->required('email', 'o e-mail')->email('email')
            ->whatsapp('whatsapp')
            ->required('role', 'o perfil')->in('role', array_keys(Auth::ROLES), 'Perfil')
            ->in('member_status', ['ativo', 'afastado', 'em_treinamento'], 'Situação')
            ->date('joined_at', 'Data de entrada')
            ->max('notes', 2000, 'Observações');

        if (!$v->fails() && User::emailExists(input('email'), $existing['id'] ?? null)) {
            $v->add('email', 'Já existe um cadastro com este e-mail.');
        }
        if ($existing && (int) $existing['id'] === Auth::id() && input('role') !== 'admin') {
            $v->add('role', 'Você não pode remover o próprio perfil de administrador.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/usuarios/' . $existing['id'] . '/editar' : '/usuarios/novo');
        }
        return [
            'name'          => input('name'),
            'email'         => input('email'),
            'whatsapp'      => input('whatsapp') !== '' ? Validator::normalizePhone(input('whatsapp')) : null,
            'role'          => input('role'),
            'member_status' => input('member_status', 'ativo') ?: 'ativo',
            'joined_at'     => input('joined_at'),
            'notes'         => input('notes'),
            'ministries'    => is_array($_POST['ministries'] ?? null) ? $_POST['ministries'] : [],
            'leader_of'     => is_array($_POST['leader_of'] ?? null) ? $_POST['leader_of'] : [],
        ];
    }

    private function handlePhoto(int $id, ?string $oldPhoto): void
    {
        try {
            if (input('remove_photo') === '1' && $oldPhoto) {
                Photo::delete($oldPhoto);
                User::setPhoto($id, null);
                return;
            }
            $new = Photo::store('photo');
            if ($new !== null) {
                Photo::delete($oldPhoto);
                User::setPhoto($id, $new);
            }
        } catch (InvalidArgumentException $e) {
            flash('warning', 'Foto não gravada: ' . $e->getMessage());
        }
    }
}
