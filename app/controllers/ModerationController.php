<?php
// app/controllers/ModerationController.php — fila de quarentena
declare(strict_types=1);

final class ModerationController
{
    public function index(): never
    {
        view('moderation/index', [
            'title'        => 'Quarentena',
            'files'        => MediaFile::quarantine(),
            'folders'      => Folder::options(),
            'restrictions' => ImageRestriction::all(true),
        ]);
    }

    public function approve(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if ($file['status'] !== 'quarentena') {
            abort(404);
        }
        $folderId = ctype_digit(input('folder_id')) ? (int) input('folder_id') : null;
        if ($folderId === null || !Folder::find($folderId)) {
            flash('danger', 'Escolha a pasta de destino para aprovar.');
            redirect('/moderacao');
        }
        $category = isset(FileTypes::CATEGORIES[input('category')]) ? input('category') : $file['category'];
        $restricted = input('has_restriction') === '1';
        $restrictionIds = is_array($_POST['restrictions'] ?? null) ? $_POST['restrictions'] : [];

        // Move do "quarantine/" para "files/" no armazenamento
        $driver = Storage::driver();
        $path = $driver->localPath($file['storage_ref']);
        $newRef = $file['storage_ref'];
        if ($path && is_file($path) && str_starts_with($file['storage_ref'], 'quarantine/')) {
            $newRef = $driver->store($path, 'files', $file['extension']);
        }

        Database::transaction(static function () use ($id, $folderId, $category, $restricted, $restrictionIds, $newRef): void {
            Database::run(
                'UPDATE files SET status = "aprovado", folder_id = :folder, category = :category, has_restriction = :r, storage_ref = :ref,
                        title = COALESCE(NULLIF(:title, ""), title), moderated_by = :by, moderated_at = NOW(), reject_reason = NULL WHERE id = :id',
                ['folder' => $folderId, 'category' => $category, 'r' => $restricted || $restrictionIds ? 1 : 0, 'ref' => $newRef,
                 'title' => mb_substr(input('title'), 0, 200), 'by' => Auth::id(), 'id' => $id]
            );
            if (input('tags') !== '') {
                Tag::sync($id, input('tags'));
            }
            MediaFile::syncRestrictions($id, $restrictionIds);
        });
        Logger::audit('arquivo_aprovado', 'files', $id, ['status' => 'quarentena'], ['status' => 'aprovado', 'pasta' => $folderId, 'restricao' => $restricted]);
        flash('success', 'Arquivo aprovado' . ($restricted || $restrictionIds ? ' com restrição de imagem (visível só a administradores)' : '') . '.');
        redirect('/moderacao');
    }

    public function reject(int $id): never
    {
        $file = MediaFile::find($id) ?? abort(404);
        if ($file['status'] !== 'quarentena') {
            abort(404);
        }
        $reason = mb_substr(input('reason'), 0, 500);
        if ($reason === '') {
            flash('danger', 'Informe o motivo da rejeição.');
            redirect('/moderacao');
        }
        MediaFile::setStatus($id, 'rejeitado', ['reject_reason' => $reason, 'moderated_by' => Auth::id(), 'moderated_at' => date('Y-m-d H:i:s')]);
        Logger::audit('arquivo_rejeitado', 'files', $id, ['status' => 'quarentena'], ['status' => 'rejeitado', 'motivo' => $reason]);
        flash('success', 'Arquivo rejeitado. Será apagado em ' . RETENTION_REJECTED_DAYS . ' dias.');
        redirect('/moderacao');
    }
}
