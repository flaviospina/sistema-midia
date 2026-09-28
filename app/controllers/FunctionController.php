<?php
// app/controllers/FunctionController.php — funções da equipe de mídia
declare(strict_types=1);

final class FunctionController
{
    public function index(): never
    {
        view('functions/index', ['title' => 'Funções da equipe', 'functions' => MediaFunction::all()]);
    }

    public function create(): never
    {
        view('functions/form', ['title' => 'Nova função', 'f' => null]);
    }

    public function store(): never
    {
        $data = $this->validate(null);
        $id = MediaFunction::create($data);
        Logger::audit('funcao_criada', 'media_functions', $id, null, $data);
        flash('success', 'Função cadastrada.');
        redirect('/funcoes');
    }

    public function edit(int $id): never
    {
        $f = MediaFunction::find($id) ?? abort(404);
        view('functions/form', ['title' => 'Editar: ' . $f['name'], 'f' => $f]);
    }

    public function update(int $id): never
    {
        $f = MediaFunction::find($id) ?? abort(404);
        $data = $this->validate($f);
        MediaFunction::update($id, $data);
        Logger::audit('funcao_alterada', 'media_functions', $id, $f, $data);
        flash('success', 'Função atualizada.');
        redirect('/funcoes');
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('name', 'o nome da função')->max('name', 80, 'Nome')
            ->max('description', 300, 'Descrição');
        $sort = input('sort_order', '0');
        if ($sort !== '' && !preg_match('/^-?\d{1,4}$/', $sort)) {
            $v->add('sort_order', 'Ordem deve ser um número.');
        }
        $slug = $existing['slug'] ?? MediaFunction::slugify(input('name'));
        if (!$v->fails() && MediaFunction::slugExists($slug, $existing['id'] ?? null)) {
            $v->add('name', 'Já existe uma função com este nome.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/funcoes/' . $existing['id'] . '/editar' : '/funcoes/novo');
        }
        return [
            'name' => input('name'), 'slug' => $slug, 'description' => input('description'),
            'sort_order' => (int) $sort, 'active' => input('active') === '1' ? 1 : 0,
        ];
    }
}
