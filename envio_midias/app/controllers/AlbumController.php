<?php
// app/controllers/AlbumController.php — álbuns (pastas por culto/evento): lista, criação com nome sugerido, edição, ZIP
declare(strict_types=1);

final class AlbumController
{
    public function home(): never
    {
        view('albums/index', ['title' => 'Álbuns', 'albums' => Album::listVisible(ctype_digit(query('ano')) ? (int) query('ano') : null, query('q')), 'years' => Album::years(), 'year' => query('ano'), 'q' => query('q'), 'recent' => Photo::recent(12), 'totals' => Auth::isTeam() ? Album::totals() : null]);
    }

    public function create(): never
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', query('data')) ? query('data') : date('Y-m-d');
        view('albums/form', ['title' => 'Nova pasta de fotos', 'a' => null, 'date' => $date, 'suggested' => Album::suggestName($date), 'weekdays' => Album::WEEKDAY_NAMES]);
    }

    public function store(): never
    {
        $v = (new Validator($_POST))->required('event_date', 'a data do evento')->date('event_date', 'Data')->required('name', 'o nome da pasta')->max('name', 120, 'Nome')->max('description', 500, 'Descrição');
        if (input('confirm') !== '1') {
            $v->add('confirm', 'Confirme a criação da pasta com o nome mostrado.');
        }
        $date = input('event_date');
        $name = preg_replace('/\s+/', ' ', input('name')) ?? input('name');
        if (!$v->fails() && Album::findByFolder(Album::folderFor($date, $name))) {
            $v->add('name', 'Já existe uma pasta com esta data e nome. Abra a pasta existente ou escolha outro nome.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/albuns/novo', ['data' => $date]);
        }
        $id = Album::create($date, $name, input('description') ?: null, input('hidden') === '1');
        Logger::audit('album_criado', 'photo_albums', $id, null, ['event_date' => $date, 'name' => $name, 'folder' => Album::folderFor($date, $name)]);
        flash('success', 'Pasta "' . Album::folderFor($date, $name) . '" criada. Agora envie as fotos.');
        redirect('/albuns/' . $id . '/enviar');
    }

    /** Sugestão de nome para uma data (usado pela tela de criação, sem recarregar). */
    public function suggest(): never
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', query('data')) ? query('data') : date('Y-m-d');
        $name = Album::suggestName($date);
        json_response(true, ['date' => $date, 'weekday' => (int) date('w', strtotime($date)), 'name' => $name, 'folder' => Album::folderFor($date, $name ?? 'Evento'), 'exists' => $name ? Album::findByFolder(Album::folderFor($date, $name)) !== null : false]);
    }

    public function show(int $id): never
    {
        $a = Album::find($id) ?? abort(404);
        if (!Album::canView($a)) {
            abort(403);
        }
        $photos = Photo::forAlbum($id);
        view('albums/show', ['title' => $a['name'], 'a' => $a, 'photos' => array_values(array_filter($photos, static fn($p) => $p['parent_id'] === null)), 'versions' => array_values(array_filter($photos, static fn($p) => $p['parent_id'] !== null)), 'zipOk' => class_exists('ZipArchive')]);
    }

    public function edit(int $id): never
    {
        $a = Album::find($id) ?? abort(404);
        view('albums/form', ['title' => 'Editar pasta', 'a' => $a, 'date' => $a['event_date'], 'suggested' => Album::suggestName($a['event_date']), 'weekdays' => Album::WEEKDAY_NAMES]);
    }

    public function update(int $id): never
    {
        $a = Album::find($id) ?? abort(404);
        $v = (new Validator($_POST))->required('name', 'o nome')->max('name', 120, 'Nome')->max('description', 500, 'Descrição');
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/albuns/' . $id . '/editar');
        }
        Album::update($id, input('name'), input('description') ?: null, input('hidden') === '1');
        if (ctype_digit(input('cover_photo_id'))) {
            $c = Photo::find((int) input('cover_photo_id'));
            if ($c && (int) $c['album_id'] === $id) {
                Album::setCover($id, (int) $c['id']);
            }
        }
        Logger::audit('album_alterado', 'photo_albums', $id, ['name' => $a['name'], 'hidden' => $a['hidden']], ['name' => input('name'), 'hidden' => input('hidden') === '1']);
        flash('success', 'Pasta atualizada. (O nome da pasta no servidor permanece ' . $a['folder'] . '.)');
        redirect('/albuns/' . $id);
    }

    public function delete(int $id): never
    {
        $a = Album::find($id) ?? abort(404);
        if ((int) $a['photos_count'] > 0 && !Auth::is('admin')) {
            flash('warning', 'Só o administrador pode excluir uma pasta com fotos.');
            redirect('/albuns/' . $id);
        }
        Album::delete($a);
        Logger::audit('album_excluido', 'photo_albums', $id, $a, null);
        flash('success', 'Pasta excluída.');
        redirect('/');
    }

    /** ZIP do álbum inteiro ou de uma seleção (ids[]). */
    public function zip(int $id): never
    {
        $a = Album::find($id) ?? abort(404);
        if (!Album::canView($a)) {
            abort(403);
        }
        if (!class_exists('ZipArchive')) {
            flash('warning', 'Este servidor não tem suporte a ZIP. Baixe as fotos individualmente.');
            redirect('/albuns/' . $id);
        }
        $ids = array_map('intval', array_filter((array) ($_POST['ids'] ?? []), 'is_numeric'));
        $photos = array_filter(Photo::forAlbum($id), static fn($p) => !$ids || in_array((int) $p['id'], $ids, true));
        if (!$photos) {
            flash('warning', 'Nenhuma foto para baixar.');
            redirect('/albuns/' . $id);
        }
        $tmp = tempnam(TMP_PATH, 'zip');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($photos as $p) {
            $path = Photo::path($p);
            if (is_file($path)) {
                $zip->addFile($path, $p['file_name']);
            }
        }
        $zip->close();
        Photo::logDownload(null, $id, 'zip');
        Logger::audit('album_zip', 'photo_albums', $id, null, ['fotos' => count($photos)]);
        register_shutdown_function(static fn() => @unlink($tmp));
        Delivery::send($tmp, 'application/zip', $a['folder'] . '.zip');
    }
}
