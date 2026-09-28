<?php
// app/controllers/EventController.php — calendário e cadastro de eventos
declare(strict_types=1);

final class EventController
{
    public function index(): never
    {
        $month = preg_match('/^\d{4}-\d{2}$/', query('mes')) ? query('mes') : date('Y-m');
        $type = isset(Event::TYPES[query('tipo')]) ? query('tipo') : '';
        $view = query('visao') === 'lista' ? 'lista' : 'calendario';

        $first = new DateTime($month . '-01');
        $last = (clone $first)->modify('last day of this month');
        $events = Event::between($first->format('Y-m-d'), (clone $last)->modify('+1 day')->format('Y-m-d'), $type);

        // Grade do calendário: semanas de domingo a sábado
        $byDay = [];
        foreach ($events as $e) {
            $byDay[substr($e['starts_at'], 0, 10)][] = $e;
        }
        $gridStart = (clone $first)->modify('-' . (int) $first->format('w') . ' days');
        $gridEnd = (clone $last)->modify('+' . (6 - (int) $last->format('w')) . ' days');
        $weeks = [];
        for ($d = clone $gridStart; $d <= $gridEnd; $d->modify('+1 day')) {
            $weeks[(int) floor($gridStart->diff($d)->days / 7)][] = [
                'date' => $d->format('Y-m-d'), 'day' => (int) $d->format('j'),
                'inMonth' => $d->format('Y-m') === $month, 'today' => $d->format('Y-m-d') === date('Y-m-d'),
                'events' => $byDay[$d->format('Y-m-d')] ?? [],
            ];
        }
        view('events/index', [
            'title'  => 'Eventos',
            'month'  => $month,
            'label'  => self::monthLabel($first),
            'prev'   => (clone $first)->modify('-1 month')->format('Y-m'),
            'next'   => (clone $first)->modify('+1 month')->format('Y-m'),
            'type'   => $type,
            'view'   => $view,
            'weeks'  => $weeks,
            'events' => $events,
        ]);
    }

    public static function monthLabel(DateTime $d): string
    {
        $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return ucfirst($months[(int) $d->format('n') - 1]) . ' de ' . $d->format('Y');
    }

    public function show(int $id): never
    {
        $event = Event::find($id) ?? abort(404);
        $data = ['title' => $event['title'], 'e' => $event, 'files' => Auth::can('files.browse') ? Event::files($id, $event['title']) : []];
        if (Auth::can('schedule.view')) {
            $data['slots'] = Event::slots($id);
            $data['assignments'] = Assignment::forEvent($id);
            $data['mine'] = Auth::can('schedule.self') ? array_filter(array_merge(...array_values($data['assignments']) ?: [[]]), static fn($a) => (int) $a['user_id'] === Auth::id()) : [];
        }
        view('events/show', $data);
    }

    public function create(): never
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', query('data')) ? query('data') : date('Y-m-d');
        view('events/form', ['title' => 'Novo evento', 'e' => null, 'date' => $date, 'ministries' => Ministry::all(true), 'templates' => ScheduleTemplate::all(true)]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = Event::create($d);
        if (ctype_digit(input('template_id')) && ScheduleTemplate::find((int) input('template_id'))) {
            Event::applyTemplate($id, (int) input('template_id'));
        }
        Logger::audit('evento_criado', 'events', $id, null, $d);
        flash('success', 'Evento criado. Defina as vagas e monte a escala.');
        redirect(Auth::can('schedule.manage') ? '/eventos/' . $id . '/escala' : '/eventos/' . $id);
    }

    public function edit(int $id): never
    {
        $e = Event::find($id) ?? abort(404);
        view('events/form', ['title' => 'Editar: ' . $e['title'], 'e' => $e, 'date' => substr($e['starts_at'], 0, 10), 'ministries' => Ministry::all(true), 'templates' => ScheduleTemplate::all(true)]);
    }

    public function update(int $id): never
    {
        $e = Event::find($id) ?? abort(404);
        $d = $this->validate($e);
        Event::update($id, $d);
        Logger::audit('evento_alterado', 'events', $id, array_intersect_key($e, $d), $d);
        flash('success', 'Evento atualizado.');
        redirect('/eventos/' . $id);
    }

    public function cancel(int $id): never
    {
        $e = Event::find($id) ?? abort(404);
        Event::setStatus($id, 'cancelado');
        Logger::audit('evento_cancelado', 'events', $id, ['status' => $e['status']], ['status' => 'cancelado', 'motivo' => input('reason')]);
        flash('success', 'Evento cancelado. As pessoas escaladas verão o cancelamento em "Minha escala".');
        redirect('/eventos/' . $id);
    }

    public function reactivate(int $id): never
    {
        $e = Event::find($id) ?? abort(404);
        Event::setStatus($id, 'agendado');
        Logger::audit('evento_reativado', 'events', $id, ['status' => $e['status']], ['status' => 'agendado']);
        flash('success', 'Evento reativado.');
        redirect('/eventos/' . $id);
    }

    public function delete(int $id): never
    {
        $e = Event::find($id) ?? abort(404);
        if ((int) $e['assigned_total'] > 0) {
            flash('warning', 'Este evento tem pessoas escaladas. Cancele-o em vez de excluir.');
            redirect('/eventos/' . $id);
        }
        Event::delete($id);
        Logger::audit('evento_excluido', 'events', $id, $e, null);
        flash('success', 'Evento excluído.');
        redirect('/eventos', ['mes' => substr($e['starts_at'], 0, 7)]);
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('title', 'o nome do evento')->max('title', 150, 'Nome')
            ->in('event_type', array_keys(Event::TYPES), 'Tipo')
            ->required('date', 'a data')->date('date', 'Data')
            ->required('time', 'o horário')
            ->max('location', 150, 'Local')->max('description', 3000, 'Descrição')->max('notes', 3000, 'Observações');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', input('time'))) {
            $v->add('time', 'Horário inválido (use HH:MM).');
        }
        $duration = ctype_digit(input('duration')) ? (int) input('duration') : 120;
        if ($duration < 15 || $duration > 24 * 60) {
            $v->add('duration', 'Duração entre 15 minutos e 24 horas.');
        }
        $ministry = ctype_digit(input('ministry_id')) ? (int) input('ministry_id') : null;
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/eventos/' . $existing['id'] . '/editar' : '/eventos/novo');
        }
        $starts = input('date') . ' ' . input('time') . ':00';
        return [
            'title' => input('title'), 'event_type' => input('event_type') ?: 'culto', 'starts_at' => $starts,
            'ends_at' => date('Y-m-d H:i:s', strtotime($starts . " +{$duration} minutes")),
            'location' => input('location') ?: null, 'description' => input('description') ?: null,
            'ministry_id' => $ministry, 'notes' => input('notes') ?: null,
        ];
    }
}
