<?php
// app/models/DownloadLog.php — registro de downloads (quem, quando, qual arquivo)
declare(strict_types=1);

final class DownloadLog
{
    public static function record(?int $fileId, string $kind, ?int $shareLinkId = null): void
    {
        try {
            Database::run(
                'INSERT INTO download_log (file_id, user_id, share_link_id, kind, ip, user_agent) VALUES (:f, :u, :s, :k, :ip, :ua)',
                ['f' => $fileId, 'u' => Auth::id(), 's' => $shareLinkId, 'k' => $kind, 'ip' => client_ip(), 'ua' => user_agent()]
            );
        } catch (Throwable $e) {
            Logger::error('Falha ao registrar download: ' . $e->getMessage());
        }
    }

    public static function forFile(int $fileId, int $limit = 50): array
    {
        return Database::all(
            'SELECT d.*, u.name AS user_name FROM download_log d LEFT JOIN users u ON u.id = d.user_id WHERE d.file_id = :f ORDER BY d.id DESC LIMIT :l',
            ['f' => $fileId, 'l' => $limit]
        );
    }

    public static function countForFile(int $fileId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM download_log WHERE file_id = :f', ['f' => $fileId]);
    }
}
