<?php
// app/controllers/ProfileController.php — "Meus dados": direitos do titular (LGPD)
declare(strict_types=1);

final class ProfileController
{
    public function index(): never
    {
        $user = Auth::user();
        view('profile/index', [
            'title'      => 'Meus dados',
            'u'          => $user,
            'functions'  => MediaFunction::forMember((int) $user['id']),
            'ministries' => User::ministries((int) $user['id']),
            'consents'   => Consent::forUser((int) $user['id']),
            'requests'   => DataRequest::forUser((int) $user['id']),
        ]);
    }

    public function update(): never
    {
        $user = Auth::user();
        $v = (new Validator($_POST))
            ->required('name', 'seu nome')->max('name', 150, 'Nome')
            ->whatsapp('whatsapp');
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/meus-dados');
        }
        $whatsapp = input('whatsapp') !== '' ? Validator::normalizePhone(input('whatsapp')) : null;
        User::updateSelf((int) $user['id'], input('name'), $whatsapp);
        try {
            if (input('remove_photo') === '1' && $user['photo_path']) {
                Photo::delete($user['photo_path']);
                User::setPhoto((int) $user['id'], null);
            } elseif (($new = Photo::store('photo')) !== null) {
                Photo::delete($user['photo_path']);
                User::setPhoto((int) $user['id'], $new);
            }
        } catch (InvalidArgumentException $e) {
            flash('warning', 'Foto não gravada: ' . $e->getMessage());
        }
        Auth::refresh();
        Logger::audit('perfil_alterado', 'users', $user['id'], User::snapshot($user), User::snapshot(Auth::user()));
        flash('success', 'Seus dados foram atualizados.');
        redirect('/meus-dados');
    }

    public function changePassword(): never
    {
        (new AuthController())->changePassword();
    }

    /** Portabilidade: exporta os dados do titular em JSON. */
    public function export(): never
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $data = [
            'exportado_em' => date('c'),
            'sistema'      => APP_NAME,
            'dados'        => [
                'nome' => $user['name'], 'email' => $user['email'], 'whatsapp' => $user['whatsapp'],
                'perfil' => Auth::roleLabel($user['role']), 'situacao_equipe' => $user['member_status'],
                'entrada_equipe' => $user['joined_at'], 'cadastrado_em' => $user['created_at'], 'ultimo_acesso' => $user['last_login_at'],
            ],
            'funcoes'        => array_values(array_map(static fn($f) => ['funcao' => $f['name'], 'nivel' => $f['level'], 'treinado_em' => $f['trained_at']], MediaFunction::forMember($id))),
            'ministerios'    => User::ministries($id),
            'consentimentos' => Consent::forUser($id),
            'solicitacoes'   => DataRequest::forUser($id),
            'auditoria'      => Database::all('SELECT action, entity, entity_id, ip, created_at FROM audit_log WHERE user_id = :u ORDER BY id DESC LIMIT 500', ['u' => $id]),
        ];
        Logger::audit('dados_exportados', 'users', $id);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus-dados-' . date('Ymd') . '.json"');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function request(): never
    {
        $user = Auth::user();
        $type = input('request_type');
        if (!isset(DataRequest::TYPES[$type])) {
            abort(422, 'Tipo de solicitação inválido.');
        }
        if (DataRequest::hasOpen((int) $user['id'], $type)) {
            flash('info', 'Você já tem uma solicitação deste tipo em aberto.');
            redirect('/meus-dados');
        }
        $id = DataRequest::create((int) $user['id'], $type, mb_substr(input('details'), 0, 2000) ?: null);
        Logger::audit('solicitacao_lgpd', 'data_requests', $id, null, ['tipo' => $type]);
        flash('success', 'Solicitação registrada. A liderança da mídia responderá em até 15 dias.');
        redirect('/meus-dados');
    }

    /** Revoga o consentimento de uso de imagem (o de cadastro é revogado via pedido de exclusão). */
    public function revoke(): never
    {
        $user = Auth::user();
        $n = Consent::revoke((int) $user['id'], 'uso_imagem');
        Logger::audit('consentimento_revogado', 'consents', $user['id'], null, ['tipo' => 'uso_imagem', 'registros' => $n]);
        flash($n ? 'success' : 'info', $n ? 'Consentimento de uso de imagem revogado.' : 'Não havia consentimento de uso de imagem ativo.');
        redirect('/meus-dados');
    }
}
