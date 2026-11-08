<?php
// app/controllers/PhotoController.php — entrega de imagens (sempre via PHP), download, exclusão, editor e versões editadas
declare(strict_types=1);

final class PhotoController
{
    private function load(int $id): array
    {
        $p = Photo::find($id) ?? abort(404);
        if ((int) $p['album_hidden'] === 1 && !Auth::can('albums.hidden')) {
            abort(403);
        }
        return $p;
    }

    public function thumb(int $id): never
    {
        $p = $this->load($id);
        $path = Photo::thumbPath($id);
        if (!is_file($path)) {
            $this->placeholder();
        }
        Delivery::send($path, 'image/jpeg', $p['file_name'], true, 86400);
    }

    public function display(int $id): never
    {
        $p = $this->load($id);
        $path = Photo::displayPath($id);
        if (!is_file($path)) {
            // Sem versão de exibição (HEIC): entrega o original se o navegador souber abrir
            Delivery::send(Photo::path($p), $p['mime'], $p['file_name'], true, 86400);
        }
        Delivery::send($path, 'image/jpeg', pathinfo($p['file_name'], PATHINFO_FILENAME) . '.jpg', true, 86400);
    }

    public function download(int $id): never
    {
        $p = $this->load($id);
        Photo::logDownload($id, (int) $p['album_id'], 'original');
        Delivery::send(Photo::path($p), $p['mime'], $p['file_name'], false);
    }

    private function placeholder(): never
    {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: private, max-age=3600');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 480"><rect width="480" height="480" fill="#f3eee2"/><path d="M160 300l60-80 50 60 40-40 60 60H160z" fill="#e6dfcd"/><circle cx="320" cy="170" r="30" fill="#e6bd4a"/><text x="240" y="430" font-family="sans-serif" font-size="26" text-anchor="middle" fill="#64748b">sem pré-visualização</text></svg>';
        exit;
    }

    public function caption(int $id): never
    {
        $p = $this->load($id);
        if (!Auth::can('albums.manage') && (int) $p['uploaded_by'] !== Auth::id()) {
            abort(403);
        }
        Photo::setCaption($id, mb_substr(input('caption'), 0, 500) ?: null);
        flash('success', 'Legenda salva.');
        redirect('/albuns/' . $p['album_id'], ['foto' => $id]);
    }

    public function delete(int $id): never
    {
        $p = $this->load($id);
        if (!Auth::can('photos.delete') && (int) $p['uploaded_by'] !== Auth::id()) {
            abort(403);
        }
        Photo::delete($p);
        Album::refreshStats((int) $p['album_id']);
        Logger::audit('foto_excluida', 'photos', $id, ['file' => $p['file_name'], 'album_id' => $p['album_id']], null);
        if (wants_json()) {
            json_response(true);
        }
        flash('success', 'Foto excluída.');
        redirect('/albuns/' . $p['album_id']);
    }

    public function setCover(int $id): never
    {
        $p = $this->load($id);
        Album::setCover((int) $p['album_id'], $id);
        flash('success', 'Capa do álbum definida.');
        redirect('/albuns/' . $p['album_id']);
    }

    // ---- Editor (melhoria de qualidade no navegador) ----------------------

    public function editor(int $id): never
    {
        $p = $this->load($id);
        // O editor trabalha no original quando o navegador consegue abri-lo; HEIC usa a versão de exibição
        $source = in_array($p['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) ? url('/fotos/' . $id . '/original') : url('/fotos/' . $id . '/imagem');
        view('photos/editor', ['title' => 'Melhorar foto · ' . $p['file_name'], 'p' => $p, 'source' => $source, 'canSave' => Auth::can('photos.save_version'), 'maxBytes' => UPLOAD_MAX_BYTES]);
    }

    /** Recebe a imagem editada (JPEG gerado no navegador) e grava como versão da foto original. */
    public function saveVersion(int $id): never
    {
        $p = $this->load($id);
        $f = $_FILES['image'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            json_response(false, null, 'Imagem não recebida.', 422);
        }
        if ((int) $f['size'] > UPLOAD_MAX_BYTES) {
            json_response(false, null, 'Imagem grande demais.', 422);
        }
        $mime = Image::detect($f['tmp_name']);
        if ($mime !== 'image/jpeg') {
            json_response(false, null, 'A versão editada deve ser JPEG.', 422);
        }
        $a = Album::find((int) $p['album_id']);
        $base = pathinfo($p['file_name'], PATHINFO_FILENAME);
        $fileName = Photo::uniqueFileName((int) $a['id'], $base . '_editada', 'jpg');
        $dest = ALBUMS_PATH . '/' . $a['folder'] . '/' . $fileName;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            json_response(false, null, 'Não foi possível gravar a versão editada.', 500);
        }
        $note = mb_substr(input('note'), 0, 255) ?: null;
        $newId = Photo::create(['album_id' => (int) $a['id'], 'parent_id' => $id, 'file_name' => $fileName, 'original_name' => $fileName, 'mime' => 'image/jpeg', 'size_bytes' => (int) filesize($dest), 'width' => null, 'height' => null, 'sha256' => hash_file('sha256', $dest), 'has_thumb' => 0, 'has_display' => 0, 'edit_note' => $note, 'caption' => $p['caption']]);
        Image::memoryOk($dest);
        $d = Image::derivatives($dest, 'image/jpeg', $newId);
        Database::run('UPDATE em_photos SET width = :w, height = :h, has_thumb = :t, has_display = :d WHERE id = :id', ['w' => $d['width'], 'h' => $d['height'], 't' => $d['has_thumb'], 'd' => $d['has_display'], 'id' => $newId]);
        Album::refreshStats((int) $a['id']);
        Logger::audit('foto_versao_editada', 'photos', $newId, null, ['parent_id' => $id, 'note' => $note]);
        json_response(true, ['id' => $newId, 'url' => url('/albuns/' . $a['id'], ['foto' => $newId]), 'thumb' => url('/fotos/' . $newId . '/miniatura')]);
    }

    /** Link público temporário (sem login) usado pela Meta para baixar a imagem na hora de publicar. */
    public function publicImage(string $token): never
    {
        $l = PublicLink::resolve($token);
        if (!$l) {
            abort(404);
        }
        $p = Photo::find((int) $l['photo_id']) ?? abort(404);
        $path = is_file(Photo::displayPath((int) $p['id'])) ? Photo::displayPath((int) $p['id']) : Photo::path($p);
        Delivery::send($path, is_file(Photo::displayPath((int) $p['id'])) ? 'image/jpeg' : $p['mime'], 'foto.jpg', true, 0);
    }
}
