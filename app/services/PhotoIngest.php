<?php
// app/services/PhotoIngest.php — entrada única de fotos (upload, Google Drive, versão editada):
// valida, grava na pasta do álbum, aplica a marca d'água (antes de qualquer ajuste) e gera miniatura/exibição.
declare(strict_types=1);

final class PhotoIngest
{
    /**
     * @param array  $album        linha de em_albums
     * @param string $tmpFile      arquivo já no servidor (será movido)
     * @param string $originalName nome original (vira nome saneado na pasta)
     * @param array  $extra        parent_id, edit_note, caption, source_ref, skip_watermark, keep_duplicate
     * @return array ['photo' => id] ou ['duplicate' => [...]] quando já existe e keep_duplicate não foi pedido
     */
    public static function store(array $album, string $tmpFile, string $originalName, array $extra = []): array
    {
        $mime = Image::detect($tmpFile);
        if ($mime === null) {
            @unlink($tmpFile);
            throw new InvalidArgumentException('O arquivo não é uma foto válida (JPG, PNG, WebP ou HEIC).');
        }
        if (filesize($tmpFile) > UPLOAD_MAX_BYTES) {
            @unlink($tmpFile);
            throw new InvalidArgumentException('Arquivo maior que o limite de ' . format_bytes(UPLOAD_MAX_BYTES) . '.');
        }
        $sha = hash_file('sha256', $tmpFile);
        $dup = Photo::duplicateInAlbum((int) $album['id'], $sha);
        if ($dup && empty($extra['keep_duplicate'])) {
            return ['duplicate' => ['id' => (int) $dup['id'], 'name' => $dup['file_name']]];
        }
        $ext = Photo::ALLOWED[$mime];
        $fileName = Photo::uniqueFileName((int) $album['id'], $originalName, $ext);
        $dir = ALBUMS_PATH . '/' . $album['folder'];
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $dest = $dir . '/' . $fileName;
        if (!@rename($tmpFile, $dest) && !@copy($tmpFile, $dest)) {
            throw new RuntimeException('Não foi possível gravar a foto na pasta do álbum.');
        }
        @unlink($tmpFile);
        @chmod($dest, 0640);
        $takenAt = Image::takenAt($dest, $mime);      // lê o EXIF antes de regravar
        $marked = empty($extra['skip_watermark']) ? Watermark::stamp($dest, $mime) : false;
        $id = Photo::create([
            'album_id' => (int) $album['id'], 'parent_id' => $extra['parent_id'] ?? null, 'file_name' => $fileName, 'original_name' => mb_substr($originalName, 0, 255),
            'mime' => $mime, 'size_bytes' => (int) filesize($dest), 'width' => null, 'height' => null, 'sha256' => $marked ? hash_file('sha256', $dest) : $sha,
            'taken_at' => $takenAt, 'has_thumb' => 0, 'has_display' => 0, 'caption' => $extra['caption'] ?? null, 'edit_note' => $extra['edit_note'] ?? null,
            'source_ref' => $extra['source_ref'] ?? null, 'watermarked' => $marked ? 1 : (int) ($extra['watermarked'] ?? 0),
        ]);
        Image::memoryOk($dest);
        $d = Image::derivatives($dest, $mime, $id);
        Database::run('UPDATE em_photos SET width = :w, height = :h, has_thumb = :t, has_display = :d WHERE id = :id', ['w' => $d['width'], 'h' => $d['height'], 't' => $d['has_thumb'], 'd' => $d['has_display'], 'id' => $id]);
        Album::refreshStats((int) $album['id']);
        return ['photo' => $id, 'file_name' => $fileName, 'watermarked' => $marked];
    }

    /** Aplica a marca d'água numa foto já importada (quando a logo foi configurada depois) e regenera os derivados. */
    public static function stampExisting(array $photo): bool
    {
        $path = Photo::path($photo);
        if (!is_file($path) || !Watermark::stamp($path, $photo['mime'])) {
            return false;
        }
        $d = Image::derivatives($path, $photo['mime'], (int) $photo['id']);
        Database::run('UPDATE em_photos SET watermarked = 1, sha256 = :s, size_bytes = :b, has_thumb = :t, has_display = :d WHERE id = :id', ['s' => hash_file('sha256', $path), 'b' => (int) filesize($path), 't' => $d['has_thumb'], 'd' => $d['has_display'], 'id' => $photo['id']]);
        return true;
    }
}
