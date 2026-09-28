<?php
// app/controllers/ScheduleController.php — montagem da escala de um evento, trocas e painel
declare(strict_types=1);

final class ScheduleController
{
    private function event(int $id): array
    {
        return Event::find($id) ?? abort(404);
    }

    public function edit(int $id): never
    {
        $e = $this->event($id);
        $slots = Event::slots($id);
        $assignments = Assignment::forEvent($id);
        $candidates = [];
        foreach ($slots as $s) {
            $fid = (int) $s['function_id'];
            if (Auth::canScheduleFunction($fid)) {
                $candidates[$fid] = Scheduler::candidates($e, $fid, false);
            }
        }
        view('schedule/edit', [
            'title'       => 'Escala: ' . $e['title'],
            'e'           => $e,
            'slots'       => $slots,
            'assignments' => $assignments,
            'candidates'  => $candidates,
            'warnings'    => Scheduler::warnings($e, $assignments),
            'functions'   => MediaFunction::all(true),
            'templates'   => ScheduleTemplate::all(true),
            'suggestion'  => query('sugerir') === '1' ? Scheduler::suggest($e) : null,
            'swaps'       => array_filter(SwapRequest::pendingForCoordinator(), static fn($s) => (int) $s['event_id'] === $id),
        ]);
    }

    public function slots(int $id): never
    {
        $this->event($id);
        $slots = [];
        foreach ((array) ($_POST['qty'] ?? []) as $fid => $qty) {
            if (ctype_digit((string) $fid) && preg_match('/^\d{1,2}$/', (string) $qty)) {
                $slots[(int) $fid] = (int) $qty;
            }
        }
        Event::syncSlots($id, $slots);
        Logger::audit('vagas_alteradas', 'events', $id, null, $slots);
        flash('success', 'Vagas atualizadas.');
        redirect('/eventos/' . $id . '/escala');
    }

    public function template(int $id): never
    {
        $this->event($id);
        $tid = ctype_digit(input('template_id')) ? (int) input('template_id') : 0;
        if (!$tid || !ScheduleTemplate::find($tid)) {
            flash('danger', 'Escolha um modelo.');
            redirect('/eventos/' . $id . '/escala');
        }
        Event::applyTemplate($id, $tid);
        Logger::audit('modelo_aplicado', 'events', $id, null, ['template_id' => $tid]);
        flash('success', 'Modelo aplicado às vagas.');
        redirect('/eventos/' . $id . '/escala');
    }

    public function assign(int $id): never
    {
        $e = $this->event($id);
        $fid = ctype_digit(input('function_id')) ? (int) input('function_id') : 0;
        $uid = ctype_digit(input('user_id')) ? (int) input('user_id') : 0;
        if (!Auth::canScheduleFunction($fid)) {
            abort(403, 'Você não coordena esta função.');
        }
        $user = User::find($uid);
        if (!$user || $user['status'] !== 'ativo' || !in_array($user['role'], Auth::MEDIA_ROLES, true)) {
            flash('danger', 'Pessoa inválida.');
            redirect('/eventos/' . $id . '/escala');
        }
        if (!isset(MediaFunction::forMember($uid)[$fid])) {
            flash('danger', $user['name'] . ' não tem esta função cadastrada.');
            redirect('/eventos/' . $id . '/escala');
        }
        if (Assignment::exists($id, $fid, $uid)) {
            flash('info', 'Esta pessoa já está nesta função.');
            redirect('/eventos/' . $id . '/escala');
        }
        $aid = Assignment::create($id, $fid, $uid);
        Logger::audit('escalado', 'assignments', $aid, null, ['event_id' => $id, 'function_id' => $fid, 'user_id' => $uid]);
        flash('success', $user['name'] . ' escalado(a). Aguardando confirmação.');
        redirect('/eventos/' . $id . '/escala');
    }

    public function remove(int $assignmentId): never
    {
        $a = Assignment::find($assignmentId) ?? abort(404);
        if (!Auth::canScheduleFunction((int) $a['function_id'])) {
            abort(403);
        }
        Assignment::delete($assignmentId);
        Logger::audit('desescalado', 'assignments', $assignmentId, $a, null);
        flash('success', $a['user_name'] . ' removido(a) da escala.');
        redirect('/eventos/' . $a['event_id'] . '/escala');
    }

    /** Aplica a sugestão automática (cria escalas pendentes). */
    public function applySuggestion(int $id): never
    {
        $e = $this->event($id);
        $n = 0;
        foreach (Scheduler::suggest($e) as $fid => $people) {
            if (!Auth::canScheduleFunction($fid)) {
                continue;
            }
            foreach ($people as $p) {
                if (!Assignment::exists($id, $fid, (int) $p['id'])) {
                    Assignment::create($id, $fid, (int) $p['id']);
                    $n++;
                }
            }
        }
        Logger::audit('sugestao_aplicada', 'events', $id, null, ['escalados' => $n]);
        flash($n ? 'success' : 'info', $n ? "{$n} pessoa(s) escalada(s) pela sugestão automática. Aguardando confirmação." : 'Nenhuma vaga aberta ou ninguém disponível.');
        redirect('/eventos/' . $id . '/escala');
    }

    // ---- Trocas (coordenador) --------------------------------------------

    public function swaps(): never
    {
        view('schedule/swaps', ['title' => 'Pedidos de troca', 'swaps' => SwapRequest::pendingForCoordinator()]);
    }

    public function approveSwap(int $id): never
    {
        $s = SwapRequest::find($id) ?? abort(404);
        if ($s['status'] !== 'aguardando_coordenador' || !Auth::canScheduleFunction((int) $s['function_id'])) {
            abort(403);
        }
        Database::transaction(static function () use ($s, $id): void {
            Assignment::reassign((int) $s['assignment_id'], (int) $s['to_user_id']);
            Assignment::respond((int) $s['assignment_id'], 'confirmado', 'Troca aprovada (' . $s['from_name'] . ' → ' . $s['to_name'] . ')');
            SwapRequest::setStatus($id, 'aprovada', Auth::id());
        });
        Logger::audit('troca_aprovada', 'swap_requests', $id, null, ['de' => $s['from_user_id'], 'para' => $s['to_user_id'], 'assignment' => $s['assignment_id']]);
        flash('success', 'Troca aprovada: ' . $s['to_name'] . ' assume ' . $s['function_name'] . ' em ' . $s['event_title'] . '.');
        redirect_back('/trocas');
    }

    public function rejectSwap(int $id): never
    {
        $s = SwapRequest::find($id) ?? abort(404);
        if ($s['status'] !== 'aguardando_coordenador' || !Auth::canScheduleFunction((int) $s['function_id'])) {
            abort(403);
        }
        SwapRequest::setStatus($id, 'rejeitada', Auth::id());
        Logger::audit('troca_rejeitada', 'swap_requests', $id);
        flash('success', 'Troca rejeitada. A escala original permanece.');
        redirect_back('/trocas');
    }

    // ---- Painel do coordenador -------------------------------------------

    public function dashboard(): never
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', query('de')) ? query('de') : date('Y-m-d', strtotime('-90 days'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', query('ate')) ? query('ate') : date('Y-m-d', strtotime('+30 days'));
        $stats = Assignment::rotationStats(SCHEDULE_ROTATION_DAYS);
        $overloaded = [];
        foreach ($stats as $uid => $st) {
            if ($st['month'] >= SCHEDULE_OVERLOAD_PER_MONTH) {
                $overloaded[] = ['user' => User::find($uid), 'month' => $st['month']];
            }
        }
        view('schedule/dashboard', [
            'title'      => 'Painel da escala',
            'from'       => $from,
            'to'         => $to,
            'open'       => Assignment::openSlotsSummary(21),
            'byUser'     => Assignment::statsByUser($from, $to . ' 23:59:59'),
            'overloaded' => $overloaded,
            'swaps'      => SwapRequest::pendingForCoordinator(),
        ]);
    }
}
