<?php
// app/controllers/FolderController.php — pastas do repositório
declare(strict_types=1);

final class FolderController
{
    public function create(): never
    {
        $parent = ctype_digit(query('pasta')) ? (int) query('pasta') : null;
        view('folders/form', ['title' => 'Nova pasta', 'folder' => null, 'parentId' => $parent, 'options' => Folder::options(), 'ministries' => Ministry::all(true)]);
    }

    public function store(): never
    {
        $data = $this->validate(null);
        $id = Folder::create($data);
        Logger::audit('pasta_criada', 'folders', $id, null, $data);
        flash('success', 'Pasta criada.');
        redirect('/pastas/' . $id);
    }

    public function edit(int $id): never
    {
        $folder = Folder::find($id) ?? abort(404);
        $options = Folder::options();
        foreach (Folder::subtreeIds($id) as $sub) {
            unset($options[$sub]); // não pode ser pai de si mesma
        }
        view('folders/form', ['title' => 'Editar pasta: ' . $folder['name'], 'folder' => $folder, 'parentId' => $folder['parent_id'], 'options' => $options, 'ministries' => Ministry::all(true)]);
    }

    public function update(int $id): never
    {
        $folder = Folder::find($id) ?? abort(404);
        $data = $this->validate($folder);
        Folder::update($id, $data);
        Logger::audit('pasta_alterada', 'folders', $id, $folder, $data);
        flash('success', 'Pasta atualizada.');
        redirect('/pastas/' . $id);
    }

    public function delete(int $id): never
    {
        $folder = Folder::find($id) ?? abort(404);
        if ((int) $folder['is_system'] === 1) {
            flash('warning', 'Esta pasta faz parte da estrutura do sistema e não pode ser excluída.');
            redirect('/pastas/' . $id);
        }
        if (Folder::children($id) || Folder::fileCount($id) > 0) {
            flash('warning', 'Esvazie a pasta (arquivos e subpastas) antes de excluí-la.');
            redirect('/pastas/' . $id);
        }
        Folder::delete($id);
        Logger::audit('pasta_excluida', 'folders', $id, $folder, null);
        flash('success', 'Pasta excluída.');
        redirect($folder['parent_id'] ? '/pastas/' . $folder['parent_id'] : '/arquivos');
    }

    private function validate(?array $existing): array
    {
        $v = (new Validator($_POST))
            ->required('name', 'o nome da pasta')->max('name', 120, 'Nome')->max('description', 500, 'Descrição')
            ->in('visibility', array_keys(Folder::VISIBILITIES), 'Visibilidade');
        $parent = ctype_digit(input('parent_id')) ? (int) input('parent_id') : null;
        if ($parent !== null && !Folder::find($parent)) {
            $v->add('parent_id', 'Pasta pai inválida.');
        }
        if ($existing && $parent !== null && in_array($parent, Folder::subtreeIds((int) $existing['id']), true)) {
            $v->add('parent_id', 'Uma pasta não pode ficar dentro dela mesma.');
        }
        $ministry = ctype_digit(input('ministry_id')) ? (int) input('ministry_id') : null;
        if ($ministry !== null && !Ministry::find($ministry)) {
            $v->add('ministry_id', 'Ministério inválido.');
        }
        if (input('visibility') === 'ministerio' && $ministry === null && Folder::effective($parent)['ministry_id'] === null) {
            $v->add('ministry_id', 'Informe o ministério dono para usar a visibilidade "ministério".');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/pastas/' . $existing['id'] . '/editar' : '/pastas/nova' . ($parent ? '?pasta=' . $parent : ''));
        }
        return [
            'parent_id' => $parent, 'name' => input('name'), 'description' => input('description'),
            'visibility' => input('visibility') ?: null, 'ministry_id' => $ministry, 'sort_order' => (int) (input('sort_order', '0') ?: 0),
        ];
    }
}
