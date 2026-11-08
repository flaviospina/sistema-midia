<?php
// app/models/Photo.php — fotos (originais e versões editadas) dentro dos álbuns
declare(strict_types=1);

final class Photo
{
    public const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heic'];

    private const SELECT = 'SELECT p.*, a.folder, a.hidden AS album_hidden, a.name AS album_name, a.event_date, u.name AS uploader_name FROM em_photos p JOIN em_albums a ON a.id = p.album_id LEFT JOIN em_users u ON u.id = p.uploaded_by';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE p.id = :id', ['id' => $id]);
    }

    public static function forAlbum(int $albumId): array
    {
        return Database::all(self::SELECT . ' WHERE p.album_id = :a ORDER BY COALESCE(p.taken_at, p.created_at), p.id', ['a' => $albumId]);
    }

    public static function versionsOf(int $photoId): array
    {
        return Database::all(self::SELECT . ' WHERE p.parent_id = :p ORDER BY p.id DESC', ['p' => $photoId]);
    }

    public static function duplicateInAlbum(int $albumId, string $sha): ?array
    {
        return Database::one('SELECT id, file_name FROM em_photos WHERE album_id = :a AND sha256 = :s LIMIT 1', ['a' => $albumId, 's' => $sha]);
    }

    /** Nome de arquivo único dentro da pasta, preservando o nome original saneado. */
    public static function uniqueFileName(int $albumId, string $original, string $ext): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base;
        $base = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $ascii) ?? '', '_');
        $base = $base !== '' ? mb_substr($base, 0, 80) : 'foto';
        $name = $base . '.' . $ext;
        $i = 1;
        while (Database::value('SELECT COUNT(*) FROM em_photos WHERE album_id = :a AND file_name = :f', ['a' => $albumId, 'f' => $name])) {
            $name = $base . '-' . (++$i) . '.' . $ext;
        }
        return $name;
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO em_photos (album_id, parent_id, file_name, original_name, mime, size_bytes, width, height, sha256, taken_at, has_thumb, has_display, caption, edit_note, uploaded_by)
             VALUES (:album_id, :parent_id, :file_name, :original_name, :mime, :size_bytes, :width, :height, :sha256, :taken_at, :has_thumb, :has_display, :caption, :edit_note, :by)',
            $d + ['parent_id' => null, 'caption' => null, 'edit_note' => null, 'taken_at' => null, 'by' => Auth::id()]
        );
    }

    public static function path(array $photo): string
    {
        return ALBUMS_PATH . '/' . $photo['folder'] . '/' . $photo['file_name'];
    }

    public static function thumbPath(int $id): string
    {
        return THUMBS_PATH . '/' . intdiv($id, 1000) . '/' . $id . '_t.jpg';
    }

    public static function displayPath(int $id): string
    {
        return THUMBS_PATH . '/' . intdiv($id, 1000) . '/' . $id . '_d.jpg';
    }

    public static function setCaption(int $id, ?string $caption): void
    {
        Database::run('UPDATE em_photos SET caption = :c WHERE id = :id', ['c' => $caption, 'id' => $id]);
    }

    public static function delete(array $photo): void
    {
        Database::transaction(static function () use ($photo): void {
            foreach (self::versionsOf((int) $photo['id']) as $v) {
                self::delete($v);
            }
            Database::run('DELETE FROM em_photos WHERE id = :id', ['id' => $photo['id']]);
            @unlink(self::path($photo));
            @unlink(self::thumbPath((int) $photo['id']));
            @unlink(self::displayPath((int) $photo['id']));
        });
    }

    public static function logDownload(?int $photoId, ?int $albumId, string $kind): void
    {
        Database::run('INSERT INTO em_downloads (photo_id, album_id, user_id, kind, ip) VALUES (:p, :a, :u, :k, :ip)', ['p' => $photoId, 'a' => $albumId, 'u' => Auth::id(), 'k' => $kind, 'ip' => client_ip()]);
    }

    /** Lista de fotos recentes para a tela inicial. */
    public static function recent(int $limit = 12): array
    {
        $hidden = Auth::can('albums.hidden') ? '' : ' AND a.hidden = 0';
        return Database::all(self::SELECT . " WHERE p.has_thumb = 1 AND p.parent_id IS NULL{$hidden} ORDER BY p.id DESC LIMIT :l", ['l' => $limit]);
    }
}
