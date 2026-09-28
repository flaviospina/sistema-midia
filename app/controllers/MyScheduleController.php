<?php
// app/controllers/MyScheduleController.php — "Minha escala": confirmar, recusar, pedir troca, ICS
declare(strict_types=1);

final class MyScheduleController
{
    public function index(): never
    {
        $uid = (int) Auth::id();
        $assignments = Assignment::forUser($uid, 30, 180);
        $swapCandidates = [];
        foreach ($assignments as $a) {
            if ($a['status'] !== 'recusado' && strtotime($a['starts_at']) > time()) {
                $swapCandidates[(int) $a['id']] = Database::all(
                    "SELECT u.id, u.name FROM member_functions mf JOIN users u ON u.id = mf.user_id JOIN media_members m ON m.user_id = u.id
                      WHERE mf.function_id = :f AND u.id <> :me AND u.status = 'ativo' AND m.member_status = 'ativo'
                        AND u.id NOT IN (SELECT user_id FROM assignments WHERE event_id = :e AND status <> 'recusado')
                      ORDER BY u.name",
                    ['f' => $a['function_id'], 'me' => $uid, 'e' => $a['event_id']]
                );
            }
        }
        view('schedule/mine', [
            'title'          => 'Minha escala',
            'assignments'    => $assignments,
            'swaps'          => SwapRequest::forUser($uid),
            'swapCandidates' => $swapCandidates,
            'openSwaps'      => array_column(array_filter(SwapRequest::forUser($uid), static fn($s) => in_array($s['status'], ['aguardando_membro', 'aguardando_coordenador'], true) && (int) $s['from_user_id'] === $uid), null, 'assignment_id'),
            'icsToken'       => Ics::tokenFor($uid),
        ]);
    }

    private function own(int $assignmentId): array
    {
        $a = Assignment::find($assignmentId) ?? abort(404);
        if ((int) $a['user_id'] !== Auth::id()) {
            abort(403);
        }
        if ($a['event_status'] !== 'agendado' || strtotime($a['starts_at']) < time()) {
            flash('warning', 'Este evento já passou ou foi cancelado.');
            redirect('/minha-escala');
        }
        return $a;
    }

    public function respond(int $id): never
    {
        $a = $this->own($id);
        $status = input('status');
        if (!in_array($status, ['confirmado', 'recusado'], true)) {
            abort(422, 'Resposta inválida.');
        }
        $note = mb_substr(input('note'), 0, 300) ?: null;
        if ($status === 'recusado' && $note === null) {
            flash('danger', 'Informe o motivo da recusa para ajudar o coordenador.');
            redirect('/minha-escala');
        }
        Assignment::respond($id, $status, $note);
        Logger::audit($status === 'confirmado' ? 'escala_confirmada' : 'escala_recusada', 'assignments', $id, ['status' => $a['status']], ['status' => $status, 'motivo' => $note]);
        flash('success', $status === 'confirmado' ? 'Presença confirmada em ' . $a['event_title'] . '.' : 'Recusa registrada. O coordenador será avisado.');
        redirect('/minha-escala');
    }

    public function requestSwap(int $id): never
    {
        $a = $this->own($id);
        if (SwapRequest::openForAssignment($id)) {
            flash('info', 'Já existe um pedido de troca em andamento para esta escala.');
            redirect('/minha-escala');
        }
        $to = ctype_digit(input('to_user_id')) ? (int) input('to_user_id') : 0;
        $target = User::find($to);
        if (!$target || $target['status'] !== 'ativo' || !isset(MediaFunction::forMember($to)[(int) $a['function_id']]) || $to === Auth::id()) {
            flash('danger', 'Escolha um colega que tenha a função ' . $a['function_name'] . '.');
            redirect('/minha-escala');
        }
        if (Assignment::exists((int) $a['event_id'], (int) $a['function_id'], $to)) {
            flash('danger', $target['name'] . ' já está escalado(a) neste evento.');
            redirect('/minha-escala');
        }
        $sid = SwapRequest::create($id, (int) Auth::id(), $to, mb_substr(input('reason'), 0, 300) ?: null);
        Logger::audit('troca_solicitada', 'swap_requests', $sid, null, ['assignment' => $id, 'para' => $to]);
        flash('success', 'Pedido enviado a ' . $target['name'] . '. Depois que aceitar, o coordenador aprova.');
        redirect('/minha-escala');
    }

    public function acceptSwap(int $id): never
    {
        $s = SwapRequest::find($id) ?? abort(404);
        if ((int) $s['to_user_id'] !== Auth::id() || $s['status'] !== 'aguardando_membro') {
            abort(403);
        }
        SwapRequest::setStatus($id, 'aguardando_coordenador');
        Logger::audit('troca_aceita_membro', 'swap_requests', $id);
        flash('success', 'Você aceitou a troca. Agora falta a aprovação do coordenador.');
        redirect('/minha-escala');
    }

    public function declineSwap(int $id): never
    {
        $s = SwapRequest::find($id) ?? abort(404);
        if ((int) $s['to_user_id'] !== Auth::id() || $s['status'] !== 'aguardando_membro') {
            abort(403);
        }
        SwapRequest::setStatus($id, 'recusada_membro');
        Logger::audit('troca_recusada_membro', 'swap_requests', $id);
        flash('info', 'Troca recusada.');
        redirect('/minha-escala');
    }

    public function cancelSwap(int $id): never
    {
        $s = SwapRequest::find($id) ?? abort(404);
        if ((int) $s['from_user_id'] !== Auth::id() || !in_array($s['status'], ['aguardando_membro', 'aguardando_coordenador'], true)) {
            abort(403);
        }
        SwapRequest::setStatus($id, 'cancelada');
        Logger::audit('troca_cancelada', 'swap_requests', $id);
        flash('info', 'Pedido de troca cancelado.');
        redirect('/minha-escala');
    }

    public function regenerateIcs(): never
    {
        Ics::regenerate((int) Auth::id());
        Logger::audit('ics_renovado', 'calendar_tokens', Auth::id());
        flash('success', 'Novo link de calendário gerado. O anterior deixou de funcionar.');
        redirect('/minha-escala');
    }

    /** Público (por token): assinatura em Google Agenda / iPhone. */
    public function ics(string $token): never
    {
        $uid = Ics::userIdByToken($token);
        $user = $uid ? User::find($uid) : null;
        if (!$user || $user['status'] !== 'ativo') {
            abort(404);
        }
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="escala-midia.ics"');
        header('Cache-Control: private, max-age=900');
        echo Ics::build($uid, $user['name']);
        exit;
    }
}
