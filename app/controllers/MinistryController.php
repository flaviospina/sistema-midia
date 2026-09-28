<?php
// app/controllers/MinistryController.php
declare(strict_types=1);

final class MinistryController
{
    public function index(): never
    {
        view('ministries/index', ['title' => 'Ministérios', 'ministries' => Ministry::all()]);
    }

    public function create(): never
    {
        view('ministries/form', ['title' => 'Novo ministério', 'm' => null, 'members' => []]);
    }

    public function store(): never
    {
        $data = $this->validate(null);
        $id = Ministry::create($data);
        Logger::audit('ministerio_criado', 'ministries', $id, null, $data);
        flash('success', 'Ministério cadastrado.');
        redirect('/ministerios');
    }

    public function edit(int $id): never
    {
        $m = Ministry::find($id) ?? abort(404);
        view('ministries/form', ['title' => 'Editar: ' . $m['name'], 'm' => $m, 'members' => Ministry::members($id)]);
    }

    public function update(int $id): never
    {
        $m = Ministry::find($id) ?? abort(404);
        $data = $this->validate($m);
        Ministry::update($id, $data);
        Logger::audit('ministerio_alterado', 'ministries', $id, $m, $data);
        flash('success', 'Ministério atualizado.');
        redirect('/ministerios');
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('name', 'o nome do ministério')->max('name', 120, 'Nome')
            ->max('description', 500, 'Descrição');
        if (!$v->fails() && Ministry::nameExists(input('name'), $existing['id'] ?? null)) {
            $v->add('name', 'Já existe um ministério com este nome.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/ministerios/' . $existing['id'] . '/editar' : '/ministerios/novo');
        }
        return ['name' => input('name'), 'description' => input('description'), 'active' => input('active') === '1' ? 1 : 0];
    }
}
