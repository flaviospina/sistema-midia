<?php
// app/models/ShareLink.php — links públicos de compartilhamento (foto ou álbum), com validade e revogação
declare(strict_types=1);

final class ShareLink
{
    public const VALIDITY = ['7' => '7 dias', '30' => '30 dias', '90' => '90 dias', '0' => 'Sem validade'];

    public static function create(?int $photoId, ?int $albumId, int $days): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        Database::run(
            'INSERT INTO share_links (token, photo_id, album_id, expires_at, created_by) VALUES (:t, :p, :a, ' . ($days > 0 ? 'DATE_ADD(NOW(), INTERVAL :d DAY)' : 'NULL') . ', :u)',
            ['t' => $token, 'p' => $photoId, 'a' => $albumId, 'u' => Auth::id()] + ($days > 0 ? ['d' => $days] : [])
        );
        return $token;
    }

    /** Link válido (não revogado, não expirado) com os dados do alvo, ou null. */
    public static function resolve(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{22}$/', $token)) {
            return null;
        }
        return Database::one(
            'SELECT l.*, a.name AS album_name, a.event_date, a.description AS album_description, a.folder, a.cover_photo_id, a.photos_count
               FROM share_links l LEFT JOIN photo_albums a ON a.id = COALESCE(l.album_id, (SELECT album_id FROM photos p WHERE p.id = l.photo_id))
              WHERE l.token = :t AND l.revoked_at IS NULL AND (l.expires_at IS NULL OR l.expires_at > NOW())',
            ['t' => $token]
        );
    }

    public static function hit(int $id): void
    {
        Database::run('UPDATE share_links SET views = views + 1 WHERE id = :id', ['id' => $id]);
    }

    public static function forAlbum(int $albumId): array
    {
        return Database::all(
            'SELECT l.*, u.name AS creator_name, p.file_name FROM share_links l LEFT JOIN users u ON u.id = l.created_by LEFT JOIN photos p ON p.id = l.photo_id
              WHERE (l.album_id = :a1 OR p.album_id = :a2) AND l.revoked_at IS NULL ORDER BY l.id DESC',
            ['a1' => $albumId, 'a2' => $albumId]
        );
    }

    public static function revoke(int $id): void
    {
        Database::run('UPDATE share_links SET revoked_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function purge(): void
    {
        Database::run('DELETE FROM share_links WHERE (expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) OR revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    }
}
