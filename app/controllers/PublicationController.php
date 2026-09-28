<?php
// app/controllers/PublicationController.php — calendário de comunicação
declare(strict_types=1);

final class PublicationController
{
    public function index(): never
    {
        $month = preg_match('/^\d{4}-\d{2}$/', query('mes')) ? query('mes') : date('Y-m');
        $channel = isset(Publication::CHANNELS[query('canal')]) ? query('canal') : '';
        $first = new DateTime($month . '-01');
        $items = Publication::between($first->format('Y-m-d'), (clone $first)->modify('+1 month')->format('Y-m-d'), $channel);
        $byDay = [];
        foreach ($items as $p) {
            $byDay[substr($p['publish_at'], 0, 10)][] = $p;
        }
        view('communication/index', [
            'title'   => 'Calendário de comunicação',
            'month'   => $month,
            'label'   => EventController::monthLabel($first),
            'prev'    => (clone $first)->modify('-1 month')->format('Y-m'),
            'next'    => (clone $first)->modify('+1 month')->format('Y-m'),
            'channel' => $channel,
            'byDay'   => $byDay,
            'items'   => $items,
        ]);
    }

    public function create(): never
    {
        view('communication/form', ['title' => 'Nova publicação', 'p' => null, 'events' => Event::forSelect(7, 120), 'team' => $this->team(), 'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', query('data')) ? query('data') : date('Y-m-d')]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = Publication::create($d);
        Logger::audit('publicacao_criada', 'publications', $id, null, $d);
        flash('success', 'Publicação agendada.');
        redirect('/comunicacao', ['mes' => substr($d['publish_at'], 0, 7)]);
    }

    public function edit(int $id): never
    {
        $p = Publication::find($id) ?? abort(404);
        view('communication/form', ['title' => 'Editar publicação', 'p' => $p, 'events' => Event::forSelect(60, 180), 'team' => $this->team(), 'date' => substr($p['publish_at'], 0, 10)]);
    }

    public function update(int $id): never
    {
        $p = Publication::find($id) ?? abort(404);
        $d = $this->validate($p);
        Publication::update($id, $d);
        Logger::audit('publicacao_alterada', 'publications', $id, array_intersect_key($p, $d), $d);
        flash('success', 'Publicação atualizada.');
        redirect('/comunicacao', ['mes' => substr($d['publish_at'], 0, 7)]);
    }

    public function publish(int $id): never
    {
        $p = Publication::find($id) ?? abort(404);
        if ($p['status'] !== 'planejado') {
            abort(404);
        }
        $link = mb_substr(input('link'), 0, 300);
        if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
            flash('danger', 'Link inválido.');
            redirect_back('/comunicacao');
        }
        Publication::markPublished($id, $link ?: null);
        if ($p['request_id'] && Publication::allPublished((int) $p['request_id'])) {
            $r = ArtRequest::find((int) $p['request_id']);
            if ($r && $r['status'] === 'aprovado') {
                ArtRequest::set((int) $r['id'], ['status' => 'publicado', 'published_at' => date('Y-m-d H:i:s')]);
                ArtWorkflow::log((int) $r['id'], 'Todas as publicações concluídas; pedido marcado como publicado.');
            }
        }
        Logger::audit('publicacao_publicada', 'publications', $id, null, ['link' => $link]);
        flash('success', 'Marcado como publicado.');
        redirect_back('/comunicacao');
    }

    public function cancel(int $id): never
    {
        $p = Publication::find($id) ?? abort(404);
        Publication::setStatus($id, $p['status'] === 'cancelado' ? 'planejado' : 'cancelado');
        Logger::audit('publicacao_status', 'publications', $id, ['status' => $p['status']], null);
        flash('success', $p['status'] === 'cancelado' ? 'Publicação reativada.' : 'Publicação cancelada.');
        redirect_back('/comunicacao');
    }

    private function team(): array
    {
        return Database::all("SELECT u.id, u.name FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.app_code = :app WHERE u.status = 'ativo' AND ur.role IN ('admin','coordenador','membro_midia') ORDER BY u.name", ['app' => APP_CODE]);
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('title', 'o título')->max('title', 150, 'Título')
            ->in('channel', array_keys(Publication::CHANNELS), 'Canal')
            ->required('date', 'a data')->date('date', 'Data')->max('notes', 500, 'Observações');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', input('time', '09:00'))) {
            $v->add('time', 'Horário inválido.');
        }
        $event = ctype_digit(input('event_id')) ? (int) input('event_id') : null;
        $resp = ctype_digit(input('responsible_id')) ? (int) input('responsible_id') : null;
        $file = ctype_digit(input('file_id')) ? (int) input('file_id') : null;
        if ($file !== null) {
            $f = MediaFile::find($file);
            if (!$f || !Access::canViewFile($f)) {
                $v->add('file_id', 'Arquivo inválido.');
            }
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/comunicacao/' . $existing['id'] . '/editar' : '/comunicacao/nova');
        }
        return [
            'title' => input('title'), 'channel' => input('channel') ?: 'instagram', 'publish_at' => input('date') . ' ' . input('time', '09:00') . ':00',
            'request_id' => $existing['request_id'] ?? null, 'event_id' => $event, 'file_id' => $file, 'responsible_id' => $resp, 'notes' => input('notes') ?: null,
        ];
    }
}
