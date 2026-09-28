<?php
// app/controllers/UnavailabilityController.php — indisponibilidades (própria e da equipe)
declare(strict_types=1);

final class UnavailabilityController
{
    public function index(): never
    {
        view('schedule/unavailability', ['title' => 'Minhas indisponibilidades', 'items' => Unavailability::forUser((int) Auth::id(), false)]);
    }

    public function store(): never
    {
        $kind = input('kind') === 'recorrente' ? 'recorrente' : 'data';
        $v = (new Validator($_POST))->max('reason', 200, 'Motivo')->date('date_from', 'Data inicial')->date('date_to', 'Data final');
        $timeFrom = input('time_from');
        $timeTo = input('time_to');
        foreach (['time_from' => $timeFrom, 'time_to' => $timeTo] as $k => $t) {
            if ($t !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
                $v->add($k, 'Horário inválido.');
            }
        }
        if (($timeFrom === '') !== ($timeTo === '')) {
            $v->add('time_to', 'Informe início e fim do horário, ou deixe os dois em branco.');
        } elseif ($timeFrom !== '' && $timeFrom >= $timeTo) {
            $v->add('time_to', 'O fim deve ser depois do início.');
        }
        $weekday = null;
        if ($kind === 'recorrente') {
            if (!preg_match('/^[0-6]$/', input('weekday'))) {
                $v->add('weekday', 'Escolha o dia da semana.');
            }
            $weekday = (int) input('weekday');
        } else {
            $v->required('date_from', 'a data');
            if (input('date_to') !== '' && input('date_to') < input('date_from')) {
                $v->add('date_to', 'A data final deve ser igual ou posterior à inicial.');
            }
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/indisponibilidades');
        }
        $d = [
            'kind' => $kind, 'date_from' => input('date_from') ?: null, 'date_to' => input('date_to') ?: null,
            'weekday' => $weekday, 'time_from' => $timeFrom ?: null, 'time_to' => $timeTo ?: null, 'reason' => input('reason') ?: null,
        ];
        $id = Unavailability::create((int) Auth::id(), $d);
        Logger::audit('indisponibilidade_criada', 'unavailability', $id, null, $d);
        flash('success', 'Indisponibilidade registrada. A sugestão automática não vai te escalar nesse período.');
        redirect('/indisponibilidades');
    }

    public function delete(int $id): never
    {
        $u = Unavailability::find($id) ?? abort(404);
        if ((int) $u['user_id'] !== Auth::id() && !Auth::can('unavailability.view')) {
            abort(403);
        }
        Unavailability::delete($id);
        Logger::audit('indisponibilidade_removida', 'unavailability', $id, $u, null);
        flash('success', 'Indisponibilidade removida.');
        redirect_back('/indisponibilidades');
    }

    public function team(): never
    {
        view('schedule/unavailability_team', ['title' => 'Indisponibilidades da equipe', 'items' => Unavailability::team()]);
    }
}
