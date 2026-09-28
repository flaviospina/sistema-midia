<?php
// app/controllers/IncidentController.php — ocorrências e relatório pós-culto
declare(strict_types=1);

final class IncidentController
{
    public function index(): never
    {
        $f = ['status' => isset(Incident::STATUSES[query('status')]) ? query('status') : '', 'kind' => isset(Incident::KINDS[query('tipo')]) ? query('tipo') : '', 'event_id' => ctype_digit(query('evento')) ? query('evento') : '', 'all' => query('todas') === '1'];
        view('incidents/index', ['title' => 'Ocorrências', 'filters' => $f, 'result' => Incident::search($f, current_page()), 'pendingReports' => Auth::can('reports.fill') ? Incident::eventsWithoutReport() : []]);
    }

    public function create(): never
    {
        view('incidents/form', ['title' => 'Nova ocorrência', 'i' => null, 'events' => Event::forSelect(30, 7), 'equipment' => Database::all("SELECT id, code, name FROM equipment WHERE status <> 'baixado' ORDER BY code"), 'eventId' => ctype_digit(query('evento')) ? (int) query('evento') : null]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = Incident::create($d);
        Logger::audit('ocorrencia_criada', 'incidents', $id, null, $d);
        if ($d['severity'] === 'alta') {
            Notifier::notify('ocorrencia.alta', Notifier::roleIds(['admin', 'coordenador']), "🚨 Ocorrência de gravidade ALTA: *{$d['title']}*" . ($d['event_id'] ? ' (' . (Event::find((int) $d['event_id'])['title'] ?? '') . ')' : '') . "\nVer: " . absolute_url('/ocorrencias/' . $id), ['incident_id' => $id]);
        }
        flash('success', 'Ocorrência registrada.');
        redirect('/ocorrencias/' . $id);
    }

    public function show(int $id): never
    {
        $i = Incident::find($id) ?? abort(404);
        view('incidents/show', ['title' => $i['title'], 'i' => $i]);
    }

    public function edit(int $id): never
    {
        $i = Incident::find($id) ?? abort(404);
        if ((int) $i['reported_by'] !== Auth::id() && !Auth::can('incidents.manage')) {
            abort(403);
        }
        view('incidents/form', ['title' => 'Editar ocorrência', 'i' => $i, 'events' => Event::forSelect(120, 7), 'equipment' => Database::all("SELECT id, code, name FROM equipment ORDER BY code"), 'eventId' => $i['event_id']]);
    }

    public function update(int $id): never
    {
        $i = Incident::find($id) ?? abort(404);
        if ((int) $i['reported_by'] !== Auth::id() && !Auth::can('incidents.manage')) {
            abort(403);
        }
        $d = $this->validate($i);
        Incident::update($id, $d);
        Logger::audit('ocorrencia_alterada', 'incidents', $id, array_intersect_key($i, $d), $d);
        flash('success', 'Ocorrência atualizada.');
        redirect('/ocorrencias/' . $id);
    }

    public function status(int $id): never
    {
        $i = Incident::find($id) ?? abort(404);
        $status = input('status');
        if (!isset(Incident::STATUSES[$status])) {
            abort(422, 'Situação inválida.');
        }
        $resolution = mb_substr(input('resolution'), 0, 3000) ?: null;
        if ($status === 'resolvida' && $resolution === null && !$i['resolution']) {
            flash('danger', 'Descreva como foi resolvida.');
            redirect('/ocorrencias/' . $id);
        }
        Incident::setStatus($id, $status, $resolution);
        Logger::audit('ocorrencia_status', 'incidents', $id, ['status' => $i['status']], ['status' => $status]);
        flash('success', 'Situação: ' . Incident::STATUSES[$status] . '.');
        redirect('/ocorrencias/' . $id);
    }

    // ---- Relatório pós-culto ------------------------------------------

    public function reportForm(int $eventId): never
    {
        $e = Event::find($eventId) ?? abort(404);
        view('incidents/report', ['title' => 'Relatório: ' . $e['title'], 'e' => $e, 'r' => Incident::report($eventId), 'incidents' => Incident::forEvent($eventId), 'progress' => Checklist::progress($eventId), 'functions' => MediaFunction::all(true)]);
    }

    public function reportSave(int $eventId): never
    {
        $e = Event::find($eventId) ?? abort(404);
        $v = (new Validator($_POST))->max('live_platform', 60, 'Plataforma')->max('summary', 5000, 'Resumo')->max('highlights', 5000, 'Destaques')->max('improvements', 5000, 'Melhorias');
        $ints = [];
        foreach (['live_peak', 'live_average', 'live_total_views', 'attendance_estimate'] as $k) {
            $val = input($k);
            if ($val !== '' && !preg_match('/^\d{1,7}$/', $val)) {
                $v->add($k, 'Informe um número inteiro.');
            }
            $ints[$k] = $val === '' ? null : (int) $val;
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/eventos/' . $eventId . '/relatorio');
        }
        $d = $ints + ['live_platform' => input('live_platform') ?: null, 'summary' => input('summary') ?: null, 'highlights' => input('highlights') ?: null, 'improvements' => input('improvements') ?: null];
        Incident::saveReport($eventId, $d);
        if ($e['status'] === 'agendado' && strtotime($e['starts_at']) < time()) {
            Event::setStatus($eventId, 'concluido');
        }
        Logger::audit('relatorio_pos_culto', 'events', $eventId, null, $d);
        flash('success', 'Relatório salvo.');
        redirect('/eventos/' . $eventId);
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))->required('title', 'o título')->max('title', 150, 'Título')->max('description', 5000, 'Descrição')
            ->in('kind', array_keys(Incident::KINDS), 'Tipo')->in('severity', array_keys(Incident::SEVERITIES), 'Gravidade');
        $event = ctype_digit(input('event_id')) ? (int) input('event_id') : null;
        if ($event !== null && !Event::find($event)) {
            $v->add('event_id', 'Evento inválido.');
        }
        $equipment = ctype_digit(input('equipment_id')) ? (int) input('equipment_id') : null;
        if ($equipment !== null && !Equipment::find($equipment)) {
            $v->add('equipment_id', 'Equipamento inválido.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/ocorrencias/' . $existing['id'] . '/editar' : '/ocorrencias/nova');
        }
        return ['event_id' => $event, 'equipment_id' => $equipment, 'kind' => input('kind') ?: 'tecnico', 'severity' => input('severity') ?: 'media', 'title' => input('title'), 'description' => input('description') ?: null];
    }
}
