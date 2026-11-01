<?php
// app/controllers/AdminController.php — usuários do sistema de fotos e auditoria
declare(strict_types=1);

final class AdminController
{
    public function users(): never
    {
        view('admin/users', ['title' => 'Usuários', 'users' => User::all(), 'others' => User::withoutRole(), 'roles' => Auth::ROLES, 'defaultPassword' => User::DEFAULT_PASSWORD]);
    }

    public function store(): never
    {
        $v = (new Validator($_POST))->required('name', 'o nome')->max('name', 150, 'Nome')->required('email', 'o e-mail')->email('email')->in('role', array_keys(Auth::ROLES), 'Perfil');
        $email = mb_strtolower(input('email'));
        if (!$v->fails() && Database::one('SELECT u.id FROM users u JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app WHERE u.email = :e', ['app' => APP_CODE, 'e' => $email])) {
            $v->add('email', 'Esta pessoa já tem acesso ao sistema de fotos.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/admin/usuarios');
        }
        $id = User::create(input('name'), $email, input('role') ?: 'membro');
        Logger::audit('usuario_criado', 'users', $id, null, ['email' => $email, 'role' => input('role')]);
        flash('success', input('name') . ' cadastrado(a). Senha do primeiro acesso: ' . User::DEFAULT_PASSWORD . ' (troca obrigatória ao entrar).');
        redirect('/admin/usuarios');
    }

    /** Dá acesso a alguém já cadastrado no banco compartilhado (Central de Mídia). */
    public function grant(): never
    {
        $id = ctype_digit(input('user_id')) ? (int) input('user_id') : 0;
        $u = $id ? Database::one('SELECT id, name FROM users WHERE id = :id', ['id' => $id]) : null;
        if (!$u || !isset(Auth::ROLES[input('role')])) {
            flash('danger', 'Pessoa ou perfil inválido.');
            redirect('/admin/usuarios');
        }
        User::setRole($id, input('role'));
        Logger::audit('usuario_acesso_concedido', 'users', $id, null, ['role' => input('role')]);
        flash('success', $u['name'] . ' agora tem acesso (perfil ' . Auth::roleLabel(input('role')) . '). Entra com a senha que já usa na Central de Mídia.');
        redirect('/admin/usuarios');
    }

    public function role(int $id): never
    {
        $u = User::find($id) ?? abort(404);
        if ($id === Auth::id()) {
            flash('warning', 'Você não pode alterar o próprio perfil.');
            redirect('/admin/usuarios');
        }
        if (!isset(Auth::ROLES[input('role')])) {
            abort(422, 'Perfil inválido.');
        }
        User::setRole($id, input('role'));
        Logger::audit('usuario_perfil', 'users', $id, ['role' => $u['role']], ['role' => input('role')]);
        flash('success', 'Perfil de ' . $u['name'] . ' atualizado.');
        redirect('/admin/usuarios');
    }

    public function toggle(int $id): never
    {
        $u = User::find($id) ?? abort(404);
        if ($id === Auth::id()) {
            flash('warning', 'Você não pode desativar a si mesmo.');
            redirect('/admin/usuarios');
        }
        $new = $u['status'] === 'ativo' ? 'inativo' : 'ativo';
        User::setStatus($id, $new);
        Logger::audit('usuario_status', 'users', $id, ['status' => $u['status']], ['status' => $new]);
        flash('success', $u['name'] . ($new === 'ativo' ? ' reativado(a).' : ' desativado(a); as sessões abertas foram encerradas.'));
        redirect('/admin/usuarios');
    }

    public function revoke(int $id): never
    {
        $u = User::find($id) ?? abort(404);
        if ($id === Auth::id()) {
            flash('warning', 'Você não pode remover o próprio acesso.');
            redirect('/admin/usuarios');
        }
        User::removeRole($id);
        Database::run('UPDATE users SET session_version = session_version + 1 WHERE id = :id', ['id' => $id]);
        Logger::audit('usuario_acesso_removido', 'users', $id, ['role' => $u['role']], null);
        flash('success', 'Acesso de ' . $u['name'] . ' ao sistema de fotos removido (o cadastro continua para os outros sistemas).');
        redirect('/admin/usuarios');
    }

    public function resetPassword(int $id): never
    {
        $u = User::find($id) ?? abort(404);
        if (input('confirm') !== '1') {
            flash('warning', 'Confirme a redefinição.');
            redirect('/admin/usuarios');
        }
        User::resetToDefault($id);
        LoginCode::forgetDevices($id);
        Logger::audit('senha_redefinida_admin', 'users', $id);
        flash('success', 'Senha de ' . $u['name'] . ' voltou para a padrão (' . User::DEFAULT_PASSWORD . '). A pessoa deverá criar uma senha nova ao entrar.');
        redirect('/admin/usuarios');
    }

    public function audit(): never
    {
        $f = ['q' => query('q'), 'action' => query('acao')];
        $conds = ['1=1'];
        $params = [];
        if ($f['q'] !== '') {
            $conds[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR l.entity LIKE :q3 OR l.after_data LIKE :q4)';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = '%' . $f['q'] . '%';
        }
        if ($f['action'] !== '') {
            $conds[] = 'l.action = :a';
            $params['a'] = $f['action'];
        }
        $where = implode(' AND ', $conds);
        $total = (int) Database::value("SELECT COUNT(*) FROM audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE {$where}", $params);
        $pg = paginate($total, current_page(), 50);
        $rows = Database::all("SELECT l.*, u.name AS user_name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE {$where} ORDER BY l.id DESC LIMIT :limit OFFSET :offset", $params + ['limit' => 50, 'offset' => $pg['offset']]);
        view('admin/audit', ['title' => 'Auditoria', 'result' => ['itens' => $rows] + $pg, 'filters' => $f, 'actions' => array_column(Database::all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action')]);
    }
}
