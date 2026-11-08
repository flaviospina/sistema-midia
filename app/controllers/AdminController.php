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

    // ---- Configurações: logo / marca d'água e Google Drive --------------

    public function settings(): never
    {
        view('admin/settings', ['title' => 'Configurações', 'hasLogo' => Watermark::hasLogo(), 'positions' => Setting::POSITIONS, 'unstamped' => Photo::countUnstamped(), 'driveReady' => Drive::ready()]);
    }

    public function saveSettings(): never
    {
        $v = (new Validator($_POST))->in('watermark_position', array_keys(Setting::POSITIONS), 'Posição')->max('google_api_key', 120, 'Chave da API');
        foreach ([['watermark_width', 5, 50, 'Tamanho'], ['watermark_opacity', 10, 100, 'Opacidade'], ['watermark_margin', 0, 10, 'Margem']] as [$k, $min, $max, $label]) {
            $val = input($k);
            if ($val !== '' && (!ctype_digit($val) || (int) $val < $min || (int) $val > $max)) {
                $v->add($k, "{$label}: use um número entre {$min} e {$max}.");
            }
        }
        $key = trim(input('google_api_key'));
        if ($key !== '' && !preg_match('/^[A-Za-z0-9_\-]{20,120}$/', $key)) {
            $v->add('google_api_key', 'Chave inválida. Copie a chave completa do Google Cloud (começa com "AIza").');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/admin/configuracoes');
        }
        try {
            if (!empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                Watermark::storeLogo($_FILES['logo']);
                Logger::audit('logo_enviada', 'em_settings', 'logo');
            }
        } catch (InvalidArgumentException | RuntimeException $e) {
            with_errors(['logo' => $e->getMessage()], $_POST);
            redirect('/admin/configuracoes');
        }
        if (input('remove_logo') === '1') {
            Watermark::removeLogo();
            Setting::set('watermark_enabled', '0');
            Logger::audit('logo_removida', 'em_settings', 'logo');
        }
        Setting::set('watermark_enabled', input('watermark_enabled') === '1' && Watermark::hasLogo() ? '1' : '0');
        Setting::set('watermark_position', input('watermark_position') ?: 'superior-direito');
        foreach (['watermark_width', 'watermark_opacity', 'watermark_margin'] as $k) {
            Setting::set($k, input($k) !== '' ? input($k) : Setting::DEFAULTS[$k]);
        }
        if (input('google_api_key_keep') !== '1' || $key !== '') {
            Setting::set('google_api_key', $key);
        }
        Logger::audit('configuracoes_salvas', 'em_settings', null, null, ['watermark_enabled' => input('watermark_enabled') === '1', 'position' => input('watermark_position'), 'google_key' => $key !== '' ? 'definida' : (input('google_api_key_keep') === '1' ? 'mantida' : 'removida')]);
        flash('success', 'Configurações salvas.' . (input('watermark_enabled') === '1' && !Watermark::hasLogo() ? ' Envie a logo para ativar a marca d\'água.' : ''));
        redirect('/admin/configuracoes');
    }

    /** Pré-visualização da marca d'água sobre um fundo neutro. */
    public function watermarkPreview(): never
    {
        $jpg = Watermark::preview();
        if ($jpg === null) {
            abort(404);
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, no-store');
        echo $jpg;
        exit;
    }

    /** Aplica a marca d'água nas fotos importadas antes da logo (em lotes, chamado pelo JS até zerar). */
    public function stampBatch(): never
    {
        if (!Watermark::enabled()) {
            json_response(false, null, 'Ative a marca d\'água e envie a logo antes.', 422);
        }
        set_time_limit(120);
        $done = 0;
        foreach (Photo::unstamped(4) as $p) {
            if (PhotoIngest::stampExisting($p)) {
                $done++;
            } else {
                Database::run('UPDATE em_photos SET watermarked = 2 WHERE id = :id', ['id' => $p['id']]); // 2 = não foi possível (não tenta de novo)
            }
        }
        $remaining = Photo::countUnstamped();
        if ($done) {
            Logger::audit('marca_dagua_aplicada_lote', 'em_photos', null, null, ['fotos' => $done, 'restantes' => $remaining]);
        }
        json_response(true, ['done' => $done, 'remaining' => $remaining]);
    }
}
