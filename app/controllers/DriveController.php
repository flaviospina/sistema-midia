<?php
// app/controllers/DriveController.php — importação automática de uma pasta compartilhada do Google Drive para um álbum
declare(strict_types=1);

final class DriveController
{
    public function page(int $albumId): never
    {
        $a = Album::find($albumId) ?? abort(404);
        view('albums/drive', ['title' => 'Importar do Google Drive · ' . $a['name'], 'a' => $a, 'ready' => Drive::ready(), 'isAdmin' => Auth::is('admin')]);
    }

    /** Passo 1: lista as imagens da pasta e marca as que já foram importadas. */
    public function listFiles(): never
    {
        $in = json_input();
        $a = Album::find((int) ($in['album_id'] ?? 0));
        if (!$a) {
            json_response(false, null, 'Pasta não encontrada.', 404);
        }
        $folderId = Drive::folderId((string) ($in['link'] ?? ''));
        if (!$folderId) {
            json_response(false, null, 'Cole o link da pasta do Google Drive (ex.: https://drive.google.com/drive/folders/…).', 422);
        }
        if (RateLimit::count('drive_list', (string) Auth::id(), 10) >= 30) {
            json_response(false, null, 'Muitas consultas ao Drive em pouco tempo. Aguarde alguns minutos.', 429);
        }
        RateLimit::hit('drive_list', (string) Auth::id());
        try {
            $folder = Drive::folderInfo($folderId);
            $files = Drive::listImages($folderId);
        } catch (Throwable $e) {
            json_response(false, null, $e->getMessage(), 422);
        }
        $done = Photo::sourceRefsInAlbum((int) $a['id']);
        foreach ($files as &$f) {
            $f['imported'] = isset($done['drive:' . $f['id']]);
            $f['size_label'] = $f['size'] ? format_bytes($f['size']) : '';
        }
        Logger::audit('drive_listado', 'em_albums', $a['id'], null, ['folder' => $folder['name'] ?? $folderId, 'arquivos' => count($files)]);
        json_response(true, ['folder' => ['id' => $folderId, 'name' => $folder['name'] ?? ''], 'files' => $files]);
    }

    /** Passo 2 (um arquivo por chamada, para caber no tempo de execução do PHP): baixa e grava no álbum. */
    public function importOne(): never
    {
        $in = json_input();
        $a = Album::find((int) ($in['album_id'] ?? 0));
        $fileId = (string) ($in['file_id'] ?? '');
        $name = trim((string) ($in['name'] ?? 'foto.jpg'));
        if (!$a || !preg_match('/^[A-Za-z0-9_-]{10,}$/', $fileId)) {
            json_response(false, null, 'Dados inválidos.', 422);
        }
        $existing = Photo::findBySource((int) $a['id'], 'drive:' . $fileId);
        if ($existing) {
            json_response(true, ['skipped' => true, 'photo' => ['id' => (int) $existing['id'], 'name' => $existing['file_name']]]);
        }
        set_time_limit(300);
        $tmp = TMP_PATH . '/drive_' . bin2hex(random_bytes(8));
        try {
            Drive::download($fileId, $tmp);
            $r = PhotoIngest::store($a, $tmp, $name !== '' ? $name : 'foto.jpg', ['source_ref' => 'drive:' . $fileId, 'keep_duplicate' => !empty($in['keep_duplicate'])]);
        } catch (Throwable $e) {
            @unlink($tmp);
            json_response(false, null, $e->getMessage(), 422);
        }
        if (isset($r['duplicate'])) {
            json_response(true, ['duplicate' => $r['duplicate']]);
        }
        Logger::audit('foto_importada_drive', 'em_photos', $r['photo'], null, ['album_id' => $a['id'], 'file' => $r['file_name'], 'drive_id' => $fileId]);
        json_response(true, ['photo' => ['id' => $r['photo'], 'name' => $r['file_name'], 'thumb' => url('/fotos/' . $r['photo'] . '/miniatura'), 'url' => url('/albuns/' . $a['id'], ['foto' => $r['photo']])]]);
    }
}
