<?php
// app/controllers/ShareController.php — links de compartilhamento (criação, listagem e acesso público)
declare(strict_types=1);

final class ShareController
{
    public function index(): never
    {
        view('share/index', ['title' => 'Links de compartilhamento', 'links' => ShareLink::all()]);
    }

    public function create(): never
    {
        $fileId = ctype_digit(input('file_id')) ? (int) input('file_id') : null;
        $folderId = ctype_digit(input('folder_id')) ? (int) input('folder_id') : null;
        if ($fileId !== null) {
            $file = MediaFile::find($fileId) ?? abort(404);
            if ($file['status'] !== 'aprovado' || !Access::canViewFile($file) || (int) $file['has_restriction'] === 1) {
                abort(403, 'Este arquivo não pode ser compartilhado.');
            }
            $back = '/arquivos/' . $fileId;
        } elseif ($folderId !== null) {
            Folder::find($folderId) ?? abort(404);
            if (!Access::canViewFolder($folderId) || Folder::effective($folderId)['visibility'] === 'restrito') {
                abort(403, 'Esta pasta não pode ser compartilhada.');
            }
            $back = '/pastas/' . $folderId;
        } else {
            abort(422, 'Nada para compartilhar.');
        }
        $days = ctype_digit(input('days')) ? (int) input('days') : 7;
        $days = max(1, min($days, 365));
        $max = ctype_digit(input('max_downloads')) && (int) input('max_downloads') > 0 ? (int) input('max_downloads') : null;
        $label = mb_substr(input('label'), 0, 150) ?: null;
        $link = ShareLink::create($fileId, $folderId, $label, date('Y-m-d H:i:s', strtotime("+{$days} days")), $max);
        Logger::audit('link_criado', 'share_links', $link['id'], null, ['file_id' => $fileId, 'folder_id' => $folderId, 'dias' => $days, 'max' => $max]);
        $_SESSION['_share_url'] = BASE_URL . url('/compartilhar/' . $link['token']);
        flash('success', 'Link criado. Válido por ' . $days . ' dia(s)' . ($max ? ", até {$max} download(s)" : '') . '.');
        redirect($back);
    }

    public function deactivate(int $id): never
    {
        $link = ShareLink::find($id) ?? abort(404);
        if (!Auth::can('files.moderate') && (int) $link['created_by'] !== Auth::id()) {
            abort(403);
        }
        ShareLink::deactivate($id);
        Logger::audit('link_desativado', 'share_links', $id);
        flash('success', 'Link desativado.');
        redirect_back('/compartilhamentos');
    }

    // ---- Acesso público por token -------------------------------------

    private function resolve(string $token): array
    {
        $link = ShareLink::findByToken($token);
        if (!$link || !ShareLink::isValid($link)) {
            abort(404, 'Este link expirou ou não existe mais.');
        }
        return $link;
    }

    public function publicPage(string $token): never
    {
        $link = $this->resolve($token);
        $files = [];
        $folder = null;
        if ($link['file_id']) {
            $f = MediaFile::find((int) $link['file_id']);
            if ($f && $f['status'] === 'aprovado' && (int) $f['has_restriction'] === 0) {
                $files = [$f];
            }
        } else {
            $folder = Folder::find((int) $link['folder_id']);
            $ids = $folder ? implode(',', Folder::subtreeIds((int) $folder['id'])) : '0';
            $files = Database::all("SELECT * FROM files WHERE folder_id IN ({$ids}) AND status = 'aprovado' AND has_restriction = 0 AND (visibility IS NULL OR visibility <> 'restrito') ORDER BY original_name");
        }
        view('share/public', ['title' => $link['label'] ?: 'Arquivos compartilhados', 'link' => $link, 'files' => $files, 'folder' => $folder, 'zip' => Zipper::available()], 'layout_auth');
    }

    public function publicFile(string $token, int $fileId): never
    {
        $link = $this->resolve($token);
        $file = $this->fileFromLink($link, $fileId);
        ShareLink::countDownload((int) $link['id']);
        DownloadLog::record($fileId, 'download', (int) $link['id']);
        $ref = $file['display_ref'] ?: $file['storage_ref'];
        Delivery::send((string) Storage::driver()->localPath($ref), FileTypes::deliveryMime($file['extension']), $file['original_name'], false);
    }

    public function publicThumb(string $token, int $fileId): never
    {
        $link = $this->resolve($token);
        $file = $this->fileFromLink($link, $fileId);
        if (!$file['thumb_ref']) {
            abort(404);
        }
        Delivery::send((string) Storage::driver()->localPath($file['thumb_ref']), 'image/jpeg', 'miniatura.jpg', true, 3600);
    }

    public function publicZip(string $token): never
    {
        $link = $this->resolve($token);
        if (!$link['folder_id']) {
            abort(404);
        }
        $ids = implode(',', Folder::subtreeIds((int) $link['folder_id']));
        $files = Database::all("SELECT * FROM files WHERE folder_id IN ({$ids}) AND status = 'aprovado' AND has_restriction = 0 AND (visibility IS NULL OR visibility <> 'restrito') ORDER BY original_name");
        ShareLink::countDownload((int) $link['id']);
        Zipper::send($files, 'compartilhado-' . date('Ymd') . '.zip');
    }

    private function fileFromLink(array $link, int $fileId): array
    {
        $file = MediaFile::find($fileId);
        if (!$file || $file['status'] !== 'aprovado' || (int) $file['has_restriction'] === 1 || $file['visibility'] === 'restrito') {
            abort(404);
        }
        if ($link['file_id'] !== null && (int) $link['file_id'] !== $fileId) {
            abort(404);
        }
        if ($link['folder_id'] !== null && (!$file['folder_id'] || !in_array((int) $file['folder_id'], Folder::subtreeIds((int) $link['folder_id']), true))) {
            abort(404);
        }
        return $file;
    }
}
