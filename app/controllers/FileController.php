<?php
// app/controllers/FileController.php — navegação, busca, ficha, edição e lixeira
declare(strict_types=1);

final class FileController
{
    public function index(): never
    {
        $folders = array_values(array_filter(Folder::children(null), static fn($f) => Access::canViewFolder((int) $f['id'])));
        view('files/index', [
            'title'     => 'Arquivos',
            'folder'    => null,
            'crumbs'    => [],
            'folders'   => $folders,
            'files'     => [],
            'view'      => $this->viewMode(),
            'order'     => query('ordem', 'recentes'),
            'mine'      => MediaFile::recentForUser((int) Auth::id(), 12),
            'tags'      => Tag::popular(20),
            'canUpload' => Access::uploadableFolders() !== [] || Auth::can('files.upload'),
        ]);
    }

    public function folder(int $id): never
    {
        $folder = Folder::find($id) ?? abort(404);
        if (!Access::canViewFolder($id)) {
            abort(403);
        }
        $order = in_array(query('ordem'), ['nome', 'tamanho', 'antigos', 'recentes'], true) ? query('ordem') : 'recentes';
        $children = array_values(array_filter(Folder::children($id), static fn($f) => Access::canViewFolder((int) $f['id'])));
        view('files/index', [
            'title'     => $folder['name'],
            'folder'    => $folder,
            'effective' => Folder::effective($id),
            'crumbs'    => Folder::breadcrumb($id),
            'folders'   => $children,
            'files'     => MediaFile::inFolder($id, $order),
            'view'      => $this->viewMode(),
            'order'     => $order,
            'canUpload' => Access::canUploadTo($id),
            'links'     => Auth::can('files.share') ? ShareLink::forTarget(null, $id) : [],
        ]);
    }

    public function search(): never
    {
        $filters = [
            'q'           => query('q'),
            'tag'         => query('tag'),
            'category'    => isset(FileTypes::CATEGORIES[query('tipo')]) ? query('tipo') : '',
            'uploader'    => ctype_digit(query('quem')) ? query('quem') : '',
            'from'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', query('de')) ? query('de') : '',
            'to'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', query('ate')) ? query('ate') : '',
            'event'       => query('evento'),
            'ministry_id' => ctype_digit(query('ministerio')) ? query('ministerio') : '',
            'folder_id'   => ctype_digit(query('pasta')) ? query('pasta') : '',
            'order'       => query('ordem', 'recentes'),
        ];
        view('files/search', [
            'title'      => 'Buscar arquivos',
            'filters'    => $filters,
            'result'     => MediaFile::search($filters, current_page()),
            'view'       => $this->viewMode(),
            'ministries' => Ministry::all(true),
            'uploaders'  => Auth::can('users.view') ? Database::all("SELECT DISTINCT u.id, u.name FROM files f JOIN users u ON u.id = f.uploaded_by WHERE u.status <> 'anonimizado' ORDER BY u.name") : [],
            'folders'    => Folder::options(static fn($f) => Access::canViewFolder((int) $f['id'])),
        ]);
    }

    public function uploadForm(): never
    {
        if (!Auth::can('files.upload')) {
            abort(403);
        }
        $folderId = ctype_digit(query('pasta')) ? (int) query('pasta') : null;
        $folders = Access::uploadableFolders();
        if ($folderId !== null && !isset($folders[$folderId])) {
            $folderId = null;
        }
        view('files/upload', [
            'title'      => 'Enviar arquivos',
            'events'     => Event::forSelect(),
            'eventId'    => ctype_digit(query('evento')) ? (int) query('evento') : null,
            'folders'    => $folders,
            'folderId'   => $folderId,
            'quota'      => Access::quotaBytes(),
            'used'       => Access::usedBytes((int) Auth::id()),
            'needsModeration' => $folders === [],
        ]);
    }

    public function show(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if (!Access::canViewFile($file)) {
            abort(403);
        }
        view('files/show', [
            'title'        => $file['title'] ?: $file['original_name'],
            'f'            => MediaFile::attachTags([$file])[0],
            'crumbs'       => Folder::breadcrumb($file['folder_id'] ? (int) $file['folder_id'] : null),
            'visibility'   => Access::fileVisibility($file),
            'canManage'    => Access::canManageFile($file),
            'restrictions' => Auth::can('restrictions.view') ? MediaFile::restrictions($id) : [],
            'links'        => Auth::can('files.share') ? ShareLink::forTarget($id, null) : [],
            'downloads'    => Auth::can('files.moderate') ? DownloadLog::forFile($id, 20) : [],
            'downloadCount'=> DownloadLog::countForFile($id),
            'duplicates'   => Auth::can('files.moderate') ? Database::all("SELECT id, original_name, folder_id, status FROM files WHERE sha256 = :s AND id <> :id AND status <> 'lixeira'", ['s' => $file['sha256'], 'id' => $id]) : [],
        ]);
    }

    public function edit(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if (!Access::canViewFile($file) || !Access::canManageFile($file)) {
            abort(403);
        }
        view('files/edit', [
            'title'        => 'Editar: ' . $file['original_name'],
            'f'            => MediaFile::attachTags([$file])[0],
            'folders'      => Auth::can('files.moderate') ? Folder::options() : Access::uploadableFolders(),
            'restrictions' => Auth::can('restrictions.view') ? ImageRestriction::all(true) : [],
            'linked'       => array_map('intval', array_column(MediaFile::restrictions($id), 'id')),
            'events'       => Event::forSelect(365, 120),
        ]);
    }

    public function update(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if (!Access::canViewFile($file) || !Access::canManageFile($file)) {
            abort(403);
        }
        $v = (new Validator($_POST))
            ->max('title', 200, 'Título')->max('description', 2000, 'Descrição')->max('event', 150, 'Evento')
            ->in('category', array_keys(FileTypes::CATEGORIES), 'Categoria')
            ->in('visibility', array_keys(Folder::VISIBILITIES), 'Visibilidade');
        $folderId = ctype_digit(input('folder_id')) ? (int) input('folder_id') : null;
        $canModerate = Auth::can('files.moderate');
        if ($folderId !== null && !$canModerate && !Access::canUploadTo($folderId)) {
            $v->add('folder_id', 'Você não pode mover para esta pasta.');
        }
        if ($folderId === null && $file['status'] === 'aprovado') {
            $v->add('folder_id', 'Escolha uma pasta.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/arquivos/' . $id . '/editar');
        }
        $restrictionIds = Auth::can('restrictions.view') && is_array($_POST['restrictions'] ?? null) ? $_POST['restrictions'] : array_map('intval', array_column(MediaFile::restrictions($id), 'id'));
        $hasRestriction = Auth::can('restrictions.view') ? (input('has_restriction') === '1' || $restrictionIds !== []) : (int) $file['has_restriction'];

        $event = ctype_digit(input('event_id')) ? Database::one('SELECT id, title FROM events WHERE id = :id', ['id' => (int) input('event_id')]) : null;
        $data = [
            'folder_id'       => $folderId,
            'title'           => input('title') ?: null,
            'description'     => input('description') ?: null,
            'category'        => input('category') ?: $file['category'],
            'visibility'      => $canModerate ? (input('visibility') ?: null) : $file['visibility'],
            'event_id'        => $event ? (int) $event['id'] : null,
            'event_name'      => $event ? $event['title'] : (input('event') ?: null),
            'has_restriction' => $hasRestriction ? 1 : 0,
        ];
        Database::transaction(static function () use ($id, $data, $restrictionIds): void {
            MediaFile::update($id, $data);
            Tag::sync($id, input('tags'));
            if (Auth::can('restrictions.view')) {
                MediaFile::syncRestrictions($id, $restrictionIds);
            }
        });
        Logger::audit('arquivo_alterado', 'files', $id, array_intersect_key($file, $data), $data);
        flash('success', 'Arquivo atualizado.');
        redirect('/arquivos/' . $id);
    }

    public function trashFile(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if (!Access::canViewFile($file) || !Access::canManageFile($file) || $file['status'] === 'lixeira') {
            abort(403);
        }
        MediaFile::setStatus($id, 'lixeira', ['trashed_at' => date('Y-m-d H:i:s'), 'trashed_by' => Auth::id()]);
        Logger::audit('arquivo_lixeira', 'files', $id, ['status' => $file['status']], ['status' => 'lixeira']);
        flash('success', 'Arquivo movido para a lixeira. Pode ser restaurado em até ' . RETENTION_TRASH_DAYS . ' dias.');
        redirect($file['folder_id'] ? '/pastas/' . $file['folder_id'] : '/arquivos');
    }

    public function restore(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if ($file['status'] !== 'lixeira' || !Auth::can('files.moderate')) {
            abort(403);
        }
        MediaFile::setStatus($id, $file['folder_id'] ? 'aprovado' : 'quarentena', ['trashed_at' => null, 'trashed_by' => null]);
        Logger::audit('arquivo_restaurado', 'files', $id);
        flash('success', 'Arquivo restaurado.');
        redirect('/arquivos/lixeira');
    }

    public function trash(): never
    {
        view('files/trash', ['title' => 'Lixeira', 'files' => MediaFile::trash(), 'rejected' => MediaFile::rejected()]);
    }

    public function emptyTrash(): never
    {
        $n = 0;
        foreach (MediaFile::trash() as $f) {
            MediaFile::purge($f);
            $n++;
        }
        Logger::audit('lixeira_esvaziada', 'files', null, null, ['removidos' => $n]);
        flash('success', "{$n} arquivo(s) apagado(s) definitivamente.");
        redirect('/arquivos/lixeira');
    }

    public function zipSelection(): never
    {
        $ids = array_map('intval', array_filter((array) ($_POST['ids'] ?? []), 'is_numeric'));
        $files = [];
        foreach (array_unique($ids) as $id) {
            $f = MediaFile::find($id);
            if ($f && $f['status'] === 'aprovado' && Access::canViewFile($f)) {
                $files[] = $f;
            }
        }
        Logger::audit('download_zip', 'files', null, null, ['ids' => array_column($files, 'id')]);
        Zipper::send($files, 'arquivos-' . date('Ymd-Hi') . '.zip');
    }

    private function viewMode(): string
    {
        $mode = query('visao');
        if (in_array($mode, ['grade', 'lista'], true)) {
            $_SESSION['files_view'] = $mode;
        }
        return $_SESSION['files_view'] ?? 'grade';
    }
}
