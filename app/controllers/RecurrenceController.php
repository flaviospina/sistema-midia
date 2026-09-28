<?php
// app/controllers/RecurrenceController.php — cultos fixos (recorrências) e modelos de escala
declare(strict_types=1);

final class RecurrenceController
{
    public function index(): never
    {
        view('schedule/recurrences', ['title' => 'Cultos fixos', 'items' => Recurrence::all(), 'weeksAhead' => SCHEDULE_WEEKS_AHEAD]);
    }

    public function create(): never
    {
        view('schedule/recurrence_form', ['title' => 'Novo culto fixo', 'r' => null, 'templates' => ScheduleTemplate::all(true)]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = Recurrence::create($d);
        $n = Recurrence::generate($id);
        Logger::audit('recorrencia_criada', 'event_recurrences', $id, null, $d + ['eventos_gerados' => $n]);
        flash('success', "Culto fixo criado; {$n} evento(s) gerado(s) para as próximas " . SCHEDULE_WEEKS_AHEAD . ' semanas.');
        redirect('/recorrencias');
    }

    public function edit(int $id): never
    {
        $r = Recurrence::find($id) ?? abort(404);
        view('schedule/recurrence_form', ['title' => 'Editar: ' . $r['title'], 'r' => $r, 'templates' => ScheduleTemplate::all(true)]);
    }

    public function update(int $id): never
    {
        $r = Recurrence::find($id) ?? abort(404);
        $d = $this->validate($r);
        Recurrence::update($id, $d);
        $pruned = 0;
        $changedSchedule = $d['weekday'] !== (int) $r['weekday'] || $d['start_time'] !== substr((string) $r['start_time'], 0, 8)
            || $d['frequency'] !== $r['frequency'] || (int) $d['active'] !== (int) $r['active'] || $d['week_of_month'] !== ($r['week_of_month'] === null ? null : (int) $r['week_of_month']);
        if ($changedSchedule) {
            // Dia/horário mudou: eventos futuros ainda sem escala são refeitos
            $pruned = Recurrence::pruneFuture($id);
        }
        $n = (int) $d['active'] ? Recurrence::generate($id) : 0;
        Logger::audit('recorrencia_alterada', 'event_recurrences', $id, $r, $d + ['removidos' => $pruned, 'gerados' => $n]);
        flash('success', 'Culto fixo atualizado.' . ($pruned ? " {$pruned} evento(s) futuro(s) sem escala refeito(s)." : ''));
        redirect('/recorrencias');
    }

    public function generate(): never
    {
        $n = Recurrence::generate();
        Logger::audit('recorrencias_geradas', 'events', null, null, ['criados' => $n]);
        flash('success', "{$n} evento(s) gerado(s).");
        redirect('/recorrencias');
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('title', 'o nome')->max('title', 150, 'Nome')
            ->in('event_type', array_keys(Event::TYPES), 'Tipo')
            ->in('frequency', array_keys(Recurrence::FREQUENCIES), 'Frequência')
            ->required('start_time', 'o horário')->max('location', 150, 'Local')
            ->required('starts_on', 'a data de início')->date('starts_on', 'Início')->date('ends_on', 'Fim');
        if (!preg_match('/^[0-6]$/', input('weekday'))) {
            $v->add('weekday', 'Escolha o dia da semana.');
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', input('start_time'))) {
            $v->add('start_time', 'Horário inválido.');
        }
        $duration = ctype_digit(input('duration_minutes')) ? (int) input('duration_minutes') : 120;
        if ($duration < 15 || $duration > 24 * 60) {
            $v->add('duration_minutes', 'Duração entre 15 minutos e 24 horas.');
        }
        $week = null;
        if (input('frequency') === 'mensal') {
            if (!in_array(input('week_of_month'), ['1', '2', '3', '4', '-1'], true)) {
                $v->add('week_of_month', 'Escolha qual semana do mês.');
            }
            $week = (int) input('week_of_month');
        }
        $template = ctype_digit(input('template_id')) ? (int) input('template_id') : null;
        if ($template !== null && !ScheduleTemplate::find($template)) {
            $v->add('template_id', 'Modelo inválido.');
        }
        if (input('ends_on') !== '' && input('ends_on') < input('starts_on')) {
            $v->add('ends_on', 'O fim deve ser posterior ao início.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/recorrencias/' . $existing['id'] . '/editar' : '/recorrencias/nova');
        }
        return [
            'title' => input('title'), 'event_type' => input('event_type') ?: 'culto', 'frequency' => input('frequency') ?: 'semanal',
            'weekday' => (int) input('weekday'), 'week_of_month' => $week, 'start_time' => input('start_time') . ':00',
            'duration_minutes' => $duration, 'location' => input('location') ?: null, 'template_id' => $template,
            'starts_on' => input('starts_on'), 'ends_on' => input('ends_on') ?: null, 'active' => input('active') === '1' ? 1 : 0,
        ];
    }

    // ---- Modelos de escala -------------------------------------------------

    public function templates(): never
    {
        view('schedule/templates', ['title' => 'Modelos de escala', 'items' => ScheduleTemplate::all()]);
    }

    public function templateCreate(): never
    {
        view('schedule/template_form', ['title' => 'Novo modelo', 't' => null, 'slots' => [], 'functions' => MediaFunction::all(true)]);
    }

    public function templateStore(): never
    {
        [$d, $slots] = $this->validateTemplate(null);
        $id = ScheduleTemplate::save(null, $d, $slots);
        Logger::audit('modelo_criado', 'schedule_templates', $id, null, $d + ['vagas' => $slots]);
        flash('success', 'Modelo criado.');
        redirect('/modelos');
    }

    public function templateEdit(int $id): never
    {
        $t = ScheduleTemplate::find($id) ?? abort(404);
        view('schedule/template_form', ['title' => 'Editar: ' . $t['name'], 't' => $t, 'slots' => ScheduleTemplate::slotMap($id), 'functions' => MediaFunction::all(true)]);
    }

    public function templateUpdate(int $id): never
    {
        $t = ScheduleTemplate::find($id) ?? abort(404);
        [$d, $slots] = $this->validateTemplate($t);
        ScheduleTemplate::save($id, $d, $slots);
        Logger::audit('modelo_alterado', 'schedule_templates', $id, $t, $d + ['vagas' => $slots]);
        flash('success', 'Modelo atualizado.');
        redirect('/modelos');
    }

    private function validateTemplate(?array $existing): array
    {
        $v = (new Validator($_POST))->required('name', 'o nome do modelo')->max('name', 100, 'Nome')->max('description', 300, 'Descrição');
        if (!$v->fails() && ScheduleTemplate::nameExists(input('name'), $existing['id'] ?? null)) {
            $v->add('name', 'Já existe um modelo com este nome.');
        }
        $slots = [];
        foreach ((array) ($_POST['qty'] ?? []) as $fid => $qty) {
            if (ctype_digit((string) $fid) && preg_match('/^\d{1,2}$/', (string) $qty) && (int) $qty > 0) {
                $slots[(int) $fid] = (int) $qty;
            }
        }
        if (!$slots) {
            $v->add('qty', 'Informe ao menos uma vaga.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/modelos/' . $existing['id'] . '/editar' : '/modelos/novo');
        }
        return [['name' => input('name'), 'description' => input('description') ?: null, 'active' => input('active', '1') === '1' ? 1 : 0], $slots];
    }
}
