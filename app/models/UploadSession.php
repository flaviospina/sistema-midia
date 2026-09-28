<?php
// app/models/UploadSession.php — sessões de upload em pedaços
declare(strict_types=1);

final class UploadSession
{
    public static function find(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }
        return Database::one('SELECT * FROM upload_sessions WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $d): string
    {
        $id = bin2hex(random_bytes(16));
        Database::run(
            'INSERT INTO upload_sessions (id, user_id, guest_upload_id, folder_id, original_name, size_bytes, chunk_size, chunks_total, meta, ip, expires_at)
             VALUES (:id, :user_id, :guest_upload_id, :folder_id, :original_name, :size_bytes, :chunk_size, :chunks_total, :meta, :ip, DATE_ADD(NOW(), INTERVAL :hours HOUR))',
            $d + ['id' => $id, 'ip' => client_ip(), 'hours' => UPLOAD_SESSION_HOURS]
        );
        return $id;
    }

    public static function setStatus(string $id, string $status, array $extra = []): void
    {
        $sets = ['status = :status'];
        $params = ['status' => $status, 'id' => $id];
        foreach ($extra as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[$k] = $v;
        }
        Database::run('UPDATE upload_sessions SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public static function expired(): array
    {
        return Database::all("SELECT * FROM upload_sessions WHERE expires_at < NOW() OR (status IN ('concluido','cancelado') AND updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY))");
    }

    public static function delete(string $id): void
    {
        Database::run('DELETE FROM upload_sessions WHERE id = :id', ['id' => $id]);
    }

    /** Bytes ainda pendentes (sessões abertas) de um usuário — entram na conta da cota. */
    public static function pendingBytes(int $userId): int
    {
        return (int) Database::value("SELECT COALESCE(SUM(size_bytes),0) FROM upload_sessions WHERE user_id = :u AND status IN ('aberto','montado','duplicado')", ['u' => $userId]);
    }
}
