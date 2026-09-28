<?php
// app/controllers/TrainingController.php — capacitação: trilha por função, progresso, validação e promoção a "apto"
declare(strict_types=1);

final class TrainingController
{
    public function index(): never
    {
        $uid = (int) Auth::id();
        view('training/index', [
            'title'      => 'Capacitação',
            'byFunction' => Training::byFunction(),
            'progress'   => Training::progressOf($uid),
            'myFunctions'=> MediaFunction::forMember($uid),
            'ready'      => Auth::can('training.manage') ? Training::readyToPromote() : [],
        ]);
    }

    public function complete(int $id): never
    {
        $t = Training::find($id) ?? abort(404);
        if (input('undo') === '1') {
            Training::uncomplete((int) Auth::id(), $id);
            flash('info', 'Marcação removida.');
        } else {
            Training::complete((int) Auth::id(), $id, mb_substr(input('notes'), 0, 300) ?: null);
            Logger::audit('treinamento_concluido', 'trainings', $id, null, ['user_id' => Auth::id()]);
            flash('success', '"' . $t['title'] . '" marcado como concluído. O coordenador valida.');
        }
        redirect('/capacitacao');
    }

    public function team(): never
    {
        view('training/team', ['title' => 'Capacitação da equipe', 'rows' => Training::teamOverview(), 'ready' => Training::readyToPromote()]);
    }

    public function person(int $userId): never
    {
        $u = User::find($userId) ?? abort(404);
        view('training/person', ['title' => 'Capacitação: ' . $u['name'], 'u' => $u, 'byFunction' => Training::byFunction(), 'progress' => Training::progressOf($userId), 'functions' => MediaFunction::forMember($userId)]);
    }

    public function validateProgress(int $userId, int $trainingId): never
    {
        User::find($userId) ?? abort(404);
        Training::find($trainingId) ?? abort(404);
        $on = input('validated') === '1';
        Training::validate($userId, $trainingId, $on);
        Logger::audit($on ? 'treinamento_validado' : 'treinamento_invalidado', 'training_progress', $trainingId, null, ['user_id' => $userId]);
        redirect('/capacitacao/pessoa/' . $userId);
    }

    /** Promove aprendiz → apto quando a trilha obrigatória está validada. */
    public function promote(int $userId, int $functionId): never
    {
        $u = User::find($userId) ?? abort(404);
        $st = Training::status($userId, $functionId);
        if (!$st['complete']) {
            flash('warning', 'A trilha obrigatória ainda não está toda validada.');
            redirect('/capacitacao/equipe');
        }
        Database::run("UPDATE member_functions SET level = 'apto', trained_at = CURDATE() WHERE user_id = :u AND function_id = :f AND level = 'aprendiz'", ['u' => $userId, 'f' => $functionId]);
        Logger::audit('promovido_apto', 'member_functions', $userId, ['level' => 'aprendiz'], ['level' => 'apto', 'function_id' => $functionId]);
        Notifier::notify('capacitacao.apto', [$userId], "🎓 Parabéns, {$u['name']}! Você concluiu a trilha de *" . (MediaFunction::find($functionId)['name'] ?? '') . "* e agora está apto(a) para ser escalado(a).", ['function_id' => $functionId]);
        flash('success', $u['name'] . ' agora está apto(a) em ' . (MediaFunction::find($functionId)['name'] ?? '') . '.');
        redirect('/capacitacao/equipe');
    }

    // ---- Configuração da trilha -----------------------------------------

    public function config(): never
    {
        view('training/config', ['title' => 'Trilhas de capacitação', 'byFunction' => Training::byFunction(false), 'functions' => MediaFunction::all(true)]);
    }

    public function create(): never
    {
        view('training/form', ['title' => 'Novo treinamento', 't' => null, 'functions' => MediaFunction::all(true), 'functionId' => ctype_digit(query('funcao')) ? (int) query('funcao') : null]);
    }

    public function store(): never
    {
        $d = $this->validate(null);
        $id = Training::create($d);
        Logger::audit('treinamento_criado', 'trainings', $id, null, $d);
        flash('success', 'Treinamento criado.');
        redirect('/capacitacao/trilhas');
    }

    public function edit(int $id): never
    {
        $t = Training::find($id) ?? abort(404);
        view('training/form', ['title' => 'Editar: ' . $t['title'], 't' => $t, 'functions' => MediaFunction::all(true), 'functionId' => $t['function_id']]);
    }

    public function update(int $id): never
    {
        $t = Training::find($id) ?? abort(404);
        $d = $this->validate($t);
        Training::update($id, $d);
        Logger::audit('treinamento_alterado', 'trainings', $id, $t, $d);
        flash('success', 'Treinamento atualizado.');
        redirect('/capacitacao/trilhas');
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))->required('title', 'o título')->max('title', 150, 'Título')->max('description', 5000, 'Descrição')->max('resource_url', 300, 'Link');
        $fid = ctype_digit(input('function_id')) ? (int) input('function_id') : 0;
        if (!$fid || !MediaFunction::find($fid)) {
            $v->add('function_id', 'Escolha a função.');
        }
        if (input('resource_url') !== '' && !filter_var(input('resource_url'), FILTER_VALIDATE_URL)) {
            $v->add('resource_url', 'Link inválido.');
        }
        $file = ctype_digit(input('file_id')) ? (int) input('file_id') : null;
        if ($file !== null && !MediaFile::find($file)) {
            $v->add('file_id', 'Arquivo inválido.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/capacitacao/trilhas/' . $existing['id'] . '/editar' : '/capacitacao/trilhas/novo');
        }
        return ['function_id' => $fid, 'title' => input('title'), 'description' => input('description') ?: null, 'resource_url' => input('resource_url') ?: null, 'file_id' => $file,
            'sort_order' => (int) (input('sort_order', '0') ?: 0), 'required' => input('required') === '1' ? 1 : 0, 'active' => input('active') === '1' ? 1 : 0];
    }
}
