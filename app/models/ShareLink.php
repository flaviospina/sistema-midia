<?php
// app/models/ShareLink.php — links de compartilhamento com token
declare(strict_types=1);

final class ShareLink
{
    public static function create(?int $fileId, ?int $folderId, ?string $label, ?string $expiresAt, ?int $maxDownloads): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
        $id = Database::insert(
            'INSERT INTO share_links (token, file_id, folder_id, label, expires_at, max_downloads, created_by)
             VALUES (:token, :file_id, :folder_id, :label, :expires_at, :max_downloads, :by)',
            ['token' => $token, 'file_id' => $fileId, 'folder_id' => $folderId, 'label' => $label, 'expires_at' => $expiresAt, 'max_downloads' => $maxDownloads, 'by' => Auth::id()]
        );
        return ['id' => $id, 'token' => $token];
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM share_links WHERE id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        return Database::one('SELECT * FROM share_links WHERE token = :t', ['t' => $token]);
    }

    /** Válido: ativo, não expirado, dentro do limite de downloads. */
    public static function isValid(array $link): bool
    {
        if ((int) $link['active'] !== 1) {
            return false;
        }
        if ($link['expires_at'] !== null && strtotime($link['expires_at']) < time()) {
            return false;
        }
        if ($link['max_downloads'] !== null && (int) $link['downloads'] >= (int) $link['max_downloads']) {
            return false;
        }
        return true;
    }

    public static function countDownload(int $id): void
    {
        Database::run('UPDATE share_links SET downloads = downloads + 1 WHERE id = :id', ['id' => $id]);
    }

    public static function deactivate(int $id): void
    {
        Database::run('UPDATE share_links SET active = 0 WHERE id = :id', ['id' => $id]);
    }

    public static function forTarget(?int $fileId, ?int $folderId): array
    {
        $col = $fileId !== null ? 'file_id' : 'folder_id';
        return Database::all(
            "SELECT s.*, u.name AS creator_name FROM share_links s LEFT JOIN users u ON u.id = s.created_by WHERE s.{$col} = :id ORDER BY s.id DESC",
            ['id' => $fileId ?? $folderId]
        );
    }

    public static function all(): array
    {
        return Database::all(
            'SELECT s.*, u.name AS creator_name, f.original_name, fo.name AS folder_name
               FROM share_links s LEFT JOIN users u ON u.id = s.created_by
          LEFT JOIN files f ON f.id = s.file_id LEFT JOIN folders fo ON fo.id = s.folder_id
           ORDER BY s.id DESC LIMIT 200'
        );
    }
}
