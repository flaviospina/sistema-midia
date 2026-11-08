<?php
// app/controllers/AdminController.php — usuários do sistema de fotos e auditoria
declare(strict_types=1);

final class AdminController
{
    public function users(): never
    {
        view('admin/users', ['title' => 'Usuários', 'users' => User::all(), 'roles' => Auth::ROLES, 'defaultPassword' => User::DEFAULT_PASSWORD]);
    }

    public function store(): never
    {
        $v = (new Validator($_POST))->required('name', 'o nome')->max('name', 150, 'Nome')->required('email', 'o e-mail')->email('email')->in('role', array_keys(Auth::ROLES), 'Perfil');
        $email = mb_strtolower(input('email'));
        if (!$v->fails() && User::emailExists($email)) {
            $v->add('email', 'Já existe um usuário com este e-mail.');
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
            flash('warning', 'Você não pode excluir o próprio usuário.');
            redirect('/admin/usuarios');
        }
        User::delete($id);
        Logger::audit('usuario_excluido', 'em_users', $id, ['email' => $u['email'], 'role' => $u['role']], null);
        flash('success', 'Usuário ' . $u['name'] . ' excluído. As fotos enviadas por ele continuam nos álbuns.');
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
        $total = (int) Database::value("SELECT COUNT(*) FROM em_audit_log l LEFT JOIN em_users u ON u.id = l.user_id WHERE {$where}", $params);
        $pg = paginate($total, current_page(), 50);
        $rows = Database::all("SELECT l.*, u.name AS user_name FROM em_audit_log l LEFT JOIN em_users u ON u.id = l.user_id WHERE {$where} ORDER BY l.id DESC LIMIT :limit OFFSET :offset", $params + ['limit' => 50, 'offset' => $pg['offset']]);
        view('admin/audit', ['title' => 'Auditoria', 'result' => ['itens' => $rows] + $pg, 'filters' => $f, 'actions' => array_column(Database::all('SELECT DISTINCT action FROM em_audit_log ORDER BY action'), 'action')]);
    }
}
