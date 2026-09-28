<?php
// app/controllers/PrivacyController.php — fila de solicitações LGPD (admin)
declare(strict_types=1);

final class PrivacyController
{
    public function index(): never
    {
        $status = query('status');
        view('privacy/index', [
            'title'    => 'Privacidade (LGPD)',
            'status'   => isset(DataRequest::STATUSES[$status]) ? $status : '',
            'requests' => DataRequest::all(isset(DataRequest::STATUSES[$status]) ? $status : ''),
        ]);
    }

    public function resolve(int $id): never
    {
        $req = DataRequest::find($id) ?? abort(404);
        if ($req['status'] !== 'aberta') {
            flash('info', 'Esta solicitação já foi tratada.');
            redirect('/privacidade');
        }
        $status = input('status');
        if (!in_array($status, ['concluida', 'recusada'], true)) {
            abort(422, 'Situação inválida.');
        }
        $notes = mb_substr(input('notes'), 0, 2000) ?: null;

        if ($status === 'concluida' && $req['request_type'] === 'exclusao' && $req['user_id']) {
            $target = User::find((int) $req['user_id']);
            if ($target && (int) $target['id'] === Auth::id()) {
                flash('warning', 'Você não pode excluir a própria conta por aqui.');
                redirect('/privacidade');
            }
            if ($target && $target['role'] === 'admin' && User::countAdmins((int) $target['id']) === 0) {
                flash('warning', 'Este é o único administrador ativo; defina outro antes de excluir.');
                redirect('/privacidade');
            }
            if ($target && $target['status'] !== 'anonimizado') {
                User::anonymize((int) $target['id']);
                Logger::audit('usuario_anonimizado', 'users', $target['id'], ['name' => $target['name'], 'email' => $target['email']], ['motivo' => 'solicitacao_lgpd#' . $id]);
            }
        }
        DataRequest::resolve($id, $status, (int) Auth::id(), $notes);
        Logger::audit('solicitacao_lgpd_resolvida', 'data_requests', $id, $req, ['status' => $status, 'notes' => $notes]);
        flash('success', 'Solicitação marcada como ' . DataRequest::STATUSES[$status] . '.');
        redirect('/privacidade');
    }
}
