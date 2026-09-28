<?php
// app/controllers/RestrictionController.php — direito de imagem (pessoas que não autorizam)
declare(strict_types=1);

final class RestrictionController
{
    public function index(): never
    {
        view('restrictions/index', ['title' => 'Restrições de imagem', 'items' => ImageRestriction::all()]);
    }

    public function create(): never
    {
        view('restrictions/form', ['title' => 'Nova restrição de imagem', 'r' => null]);
    }

    public function store(): never
    {
        $data = $this->validate();
        $id = ImageRestriction::create($data);
        $this->handlePhoto($id, null);
        Logger::audit('restricao_criada', 'image_restrictions', $id, null, $data);
        flash('success', 'Restrição cadastrada.');
        redirect('/restricoes');
    }

    public function edit(int $id): never
    {
        $r = ImageRestriction::find($id) ?? abort(404);
        view('restrictions/form', ['title' => 'Editar: ' . $r['person_name'], 'r' => $r]);
    }

    public function update(int $id): never
    {
        $r = ImageRestriction::find($id) ?? abort(404);
        $data = $this->validate($r);
        ImageRestriction::update($id, $data);
        $this->handlePhoto($id, $r['photo_path']);
        Logger::audit('restricao_alterada', 'image_restrictions', $id, $r, $data);
        flash('success', 'Restrição atualizada.');
        redirect('/restricoes');
    }

    public function photo(int $id): never
    {
        $r = ImageRestriction::find($id) ?? abort(404);
        $path = $r['photo_path'] && preg_match('/^[a-f0-9]{32}\.jpg$/', $r['photo_path']) ? STORAGE_PATH . '/restrictions/' . $r['photo_path'] : null;
        if (!$path || !is_file($path)) {
            abort(404);
        }
        Delivery::send($path, 'image/jpeg', 'referencia.jpg', true, 600);
    }

    private function validate(?array $existing = null): array
    {
        $v = (new Validator($_POST))
            ->required('person_name', 'o nome da pessoa')->max('person_name', 150, 'Nome')
            ->max('guardian_name', 150, 'Responsável')->max('contact', 100, 'Contato')->max('notes', 2000, 'Observações');
        if (input('is_minor') === '1' && input('guardian_name') === '') {
            $v->add('guardian_name', 'Para menores, informe o nome do responsável.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect($existing ? '/restricoes/' . $existing['id'] . '/editar' : '/restricoes/nova');
        }
        return [
            'person_name' => input('person_name'), 'is_minor' => input('is_minor') === '1' ? 1 : 0,
            'guardian_name' => input('guardian_name') ?: null, 'contact' => input('contact') ?: null,
            'notes' => input('notes') ?: null, 'active' => input('active', '1') === '1' ? 1 : 0,
        ];
    }

    /** Foto de referência: mesmo tratamento da foto de perfil (GD, sem EXIF), em storage/restrictions. */
    private function handlePhoto(int $id, ?string $old): void
    {
        try {
            if (input('remove_photo') === '1' && $old) {
                @unlink(STORAGE_PATH . '/restrictions/' . $old);
                ImageRestriction::setPhoto($id, null);
                return;
            }
            $new = Photo::store('photo');
            if ($new !== null) {
                $dir = STORAGE_PATH . '/restrictions';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0750, true);
                }
                @rename(STORAGE_PATH . '/photos/' . $new, $dir . '/' . $new);
                if ($old) {
                    @unlink($dir . '/' . $old);
                }
                ImageRestriction::setPhoto($id, $new);
            }
        } catch (InvalidArgumentException $e) {
            flash('warning', 'Foto não gravada: ' . $e->getMessage());
        }
    }
}
