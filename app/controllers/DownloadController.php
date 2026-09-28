<?php
// app/controllers/DownloadController.php — entrega de arquivos com checagem de permissão (nunca link direto)
declare(strict_types=1);

final class DownloadController
{
    private function load(int $id): array
    {
        $file = MediaFile::find($id) ?? abort(404);
        if (!Access::canViewFile($file)) {
            abort(403);
        }
        return $file;
    }

    /** Download (versão exibida para não-admin quando existir). */
    public function download(int $id): never
    {
        $file = $this->load($id);
        $ref = ($file['display_ref'] && !Auth::can('files.original')) ? $file['display_ref'] : $file['storage_ref'];
        DownloadLog::record($id, 'download');
        Delivery::send((string) Storage::driver()->localPath($ref), FileTypes::deliveryMime($file['extension']), $file['original_name'], false);
    }

    /** Original com EXIF (só admin). */
    public function original(int $id): never
    {
        if (!Auth::can('files.original')) {
            abort(403);
        }
        $file = $this->load($id);
        DownloadLog::record($id, 'original');
        Delivery::send((string) Storage::driver()->localPath($file['storage_ref']), FileTypes::deliveryMime($file['extension']), $file['original_name'], false);
    }

    /** Pré-visualização inline (imagem, vídeo com Range, áudio, PDF). */
    public function view(int $id): never
    {
        $file = $this->load($id);
        if (!FileTypes::isPreviewable($file['mime'], $file['extension'])) {
            abort(404, 'Este tipo de arquivo não tem pré-visualização.');
        }
        $ref = $file['display_ref'] ?: $file['storage_ref'];
        if (!isset($_GET['range_seen'])) {
            // registra só a primeira requisição (players fazem várias com Range)
            if (empty($_SERVER['HTTP_RANGE']) || str_starts_with($_SERVER['HTTP_RANGE'], 'bytes=0-')) {
                DownloadLog::record($id, 'visualizacao');
            }
        }
        Delivery::send((string) Storage::driver()->localPath($ref), FileTypes::deliveryMime($file['extension']), $file['original_name'], true, 3600);
    }

    public function thumb(int $id): never
    {
        $file = $this->load($id);
        if (!$file['thumb_ref']) {
            abort(404);
        }
        Delivery::send((string) Storage::driver()->localPath($file['thumb_ref']), 'image/jpeg', 'miniatura.jpg', true, 86400);
    }

    public function zipFolder(int $id): never
    {
        Folder::find($id) ?? abort(404);
        if (!Access::canViewFolder($id)) {
            abort(403);
        }
        $files = MediaFile::approvedInSubtree($id);
        Logger::audit('download_zip', 'folders', $id, null, ['arquivos' => count($files)]);
        Zipper::send($files, Tag::slugify(Folder::find($id)['name']) . '-' . date('Ymd') . '.zip');
    }
}
