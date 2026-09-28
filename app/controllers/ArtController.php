<?php
// app/controllers/ArtController.php — pedidos de arte: listagem, kanban, ficha, fluxo, checklist
declare(strict_types=1);

final class ArtController
{
    private function load(int $id): array
    {
        $r = ArtRequest::find($id) ?? abort(404);
        if (!ArtRequest::canView($r)) {
            abort(403);
        }
        return $r;
    }

    public function index(): never
    {
        $f = [
            'status'      => isset(ArtRequest::STATUSES[query('status')]) ? query('status') : '',
            'ministry_id' => ctype_digit(query('ministerio')) ? query('ministerio') : '',
            'designer_id' => ctype_digit(query('designer')) ? query('designer') : '',
            'q'           => query('q'),
            'mine'        => query('meus') === '1',
            'all'         => query('todos') === '1',
        ];
        view('art/index', [
            'title'      => 'Pedidos de arte',
            'filters'    => $f,
            'result'     => ArtRequest::search($f, current_page()),
            'ministries' => Ministry::all(true),
            'designers'  => Auth::can('art.view_all') ? Database::all("SELECT DISTINCT u.id, u.name FROM art_requests r JOIN users u ON u.id = r.designer_id ORDER BY u.name") : [],
        ]);
    }

    public function kanban(): never
    {
        view('art/kanban', ['title' => 'Kanban de artes', 'columns' => ArtRequest::kanban()]);
    }

    public function delays(): never
    {
        view('art/delays', ['title' => 'Atrasos e prazos', 'items' => ArtRequest::delays(), 'counts' => ArtRequest::countsForDashboard(), 'ministries' => ArtRequest::topMinistries()]);
    }

    public function create(): never
    {
        view('art/form', ['title' => 'Novo pedido de arte', 'r' => null, 'ministries' => $this->ministryOptions(), 'events' => Event::forSelect(7, 180)]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = ArtRequest::create($d + ['requester_id' => Auth::id()]);
        ArtWorkflow::log($id, 'Pedido aberto por ' . Auth::user()['name'] . '.');
        Logger::audit('arte_pedido_criado', 'art_requests', $id, null, $d);
        flash('success', 'Pedido enviado à equipe de mídia.' . ($d['is_urgent'] ? ' Atenção: o prazo é menor que ' . ART_MIN_DAYS . ' dias; a equipe fará o possível.' : ''));
        redirect('/artes/' . $id);
    }

    public function show(int $id): never
    {
        $r = $this->load($id);
        view('art/show', [
            'title'        => $r['title'],
            'r'            => $r,
            'versions'     => ArtRequest::versions($id),
            'attachments'  => ArtRequest::attachments($id),
            'comments'     => ArtRequest::comments($id),
            'approvals'    => ArtRequest::approvals($id),
            'actions'      => ArtWorkflow::actions($r),
            'checklist'    => Auth::can('art.produce') ? ArtRequest::checklistItems() : [],
            'checks'       => ArtRequest::checks($id),
            'publications' => Publication::forRequest($id),
            'canAttach'    => ArtWorkflow::canUpload($r, 'anexo'),
            'canVersion'   => ArtWorkflow::canUpload($r, 'versao'),
            'canEdit'      => (ArtRequest::isRequester($r) && in_array($r['status'], ['recebido', 'em_producao', 'ajustes'], true)) || Auth::can('art.manage'),
            'designers'    => Auth::can('art.manage') ? Database::all("SELECT u.id, u.name FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.app_code = :app WHERE u.status = 'ativo' AND ur.role IN ('admin','coordenador','membro_midia') ORDER BY u.name", ['app' => APP_CODE]) : [],
        ]);
    }

    public function edit(int $id): never
    {
        $r = $this->load($id);
        if (!((ArtRequest::isRequester($r) && in_array($r['status'], ['recebido', 'em_producao', 'ajustes'], true)) || Auth::can('art.manage'))) {
            abort(403);
        }
        view('art/form', ['title' => 'Editar pedido', 'r' => $r, 'ministries' => $this->ministryOptions(), 'events' => Event::forSelect(30, 180)]);
    }

    public function update(int $id): never
    {
        $r = $this->load($id);
        if (!((ArtRequest::isRequester($r) && in_array($r['status'], ['recebido', 'em_producao', 'ajustes'], true)) || Auth::can('art.manage'))) {
            abort(403);
        }
        $d = $this->validate($r);
        ArtRequest::update($id, $d);
        ArtWorkflow::log($id, 'Briefing alterado por ' . Auth::user()['name'] . '.');
        Logger::audit('arte_pedido_alterado', 'art_requests', $id, array_intersect_key($r, $d), $d);
        flash('success', 'Pedido atualizado.');
        redirect('/artes/' . $id);
    }

    public function action(int $id): never
    {
        $r = $this->load($id);
        try {
            $msg = ArtWorkflow::apply($r, input('action'), input('notes'));
        } catch (InvalidArgumentException $e) {
            flash('danger', $e->getMessage());
            redirect('/artes/' . $id);
        }
        flash('success', $msg);
        redirect('/artes/' . $id);
    }

    public function assign(int $id): never
    {
        $r = $this->load($id);
        $uid = ctype_digit(input('designer_id')) ? (int) input('designer_id') : null;
        $u = $uid ? User::find($uid) : null;
        if ($uid && (!$u || !in_array($u['role'], Auth::MEDIA_ROLES, true) || $u['status'] !== 'ativo')) {
            flash('danger', 'Designer inválido.');
            redirect('/artes/' . $id);
        }
        $fields = ['designer_id' => $uid];
        if ($uid && $r['status'] === 'recebido') {
            $fields['status'] = 'em_producao';
        }
        ArtRequest::set($id, $fields);
        ArtWorkflow::log($id, $u ? 'Designer definido: ' . $u['name'] . '.' : 'Designer removido.');
        flash('success', $u ? $u['name'] . ' é o(a) designer deste pedido.' : 'Designer removido.');
        redirect('/artes/' . $id);
    }

    public function comment(int $id): never
    {
        $r = $this->load($id);
        $body = mb_substr(input('body'), 0, 3000);
        if ($body === '') {
            flash('danger', 'Escreva o comentário.');
            redirect('/artes/' . $id);
        }
        $vid = ctype_digit(input('version_id')) ? (int) input('version_id') : null;
        ArtRequest::comment($id, $body, 'comentario', $vid);
        Logger::audit('arte_comentario', 'art_requests', $id, null, ['versao' => $vid]);
        flash('success', 'Comentário registrado.');
        redirect('/artes/' . $id);
    }

    public function checklist(int $id): never
    {
        $r = $this->load($id);
        if (!Auth::can('art.produce')) {
            abort(403);
        }
        ArtRequest::saveChecks($id, (array) ($_POST['items'] ?? []));
        $ok = ArtRequest::checklistComplete($id);
        Logger::audit('arte_checklist', 'art_requests', $id, null, ['completo' => $ok]);
        flash('success', $ok ? 'Checklist completo.' : 'Checklist salvo (ainda incompleto).');
        redirect('/artes/' . $id);
    }

    public function checklistConfig(): never
    {
        view('art/checklist', ['title' => 'Checklist de identidade visual', 'items' => ArtRequest::checklistItems(false)]);
    }

    public function checklistSave(): never
    {
        ArtRequest::saveChecklistItems((array) ($_POST['items'] ?? []));
        Logger::audit('arte_checklist_config', 'art_checklist_items', null);
        flash('success', 'Checklist atualizado.');
        redirect('/artes/checklist');
    }

    private function ministryOptions(): array
    {
        $all = Ministry::all(true);
        if (Auth::can('art.view_all')) {
            return $all;
        }
        $mine = Auth::ministryIds();
        return array_values(array_filter($all, static fn($m) => in_array((int) $m['id'], $mine, true)));
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('title', 'o título')->max('title', 150, 'Título')
            ->required('briefing', 'o briefing')->max('briefing', 5000, 'Briefing')->max('texts', 5000, 'Textos')
            ->required('publish_on', 'a data de publicação')->date('publish_on', 'Data de publicação');
        $formats = array_values(array_intersect(array_keys(ArtRequest::FORMATS), array_map('strval', (array) ($_POST['formats'] ?? []))));
        if (!$formats) {
            $v->add('formats', 'Escolha ao menos um formato.');
        }
        if (input('publish_on') !== '' && input('publish_on') < date('Y-m-d')) {
            $v->add('publish_on', 'A data de publicação já passou.');
        }
        $ministry = ctype_digit(input('ministry_id')) ? (int) input('ministry_id') : null;
        if ($ministry !== null && !in_array($ministry, array_map(static fn($m) => (int) $m['id'], $this->ministryOptions()), true)) {
            $v->add('ministry_id', 'Ministério inválido.');
        }
        if ($ministry === null && Auth::is('lider_ministerio')) {
            $v->add('ministry_id', 'Informe o ministério.');
        }
        $event = ctype_digit(input('event_id')) ? (int) input('event_id') : null;
        if ($event !== null && !Event::find($event)) {
            $v->add('event_id', 'Evento inválido.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/artes/' . $existing['id'] . '/editar' : '/artes/novo');
        }
        $needs = ArtRequest::needsPastoral($formats);
        if ($existing && Auth::can('art.manage') && input('needs_pastoral_override') !== '') {
            $needs = input('needs_pastoral_override') === '1';
        }
        return [
            'title' => input('title'), 'ministry_id' => $ministry, 'event_id' => $event, 'briefing' => input('briefing'), 'texts' => input('texts') ?: null,
            'formats' => implode(',', $formats), 'publish_on' => input('publish_on'), 'is_urgent' => ArtRequest::isUrgent(input('publish_on')) ? 1 : 0,
            'needs_pastoral' => $needs ? 1 : 0,
        ];
    }
}
