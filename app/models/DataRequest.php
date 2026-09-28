<?php
// app/models/DataRequest.php — solicitações do titular (LGPD)
declare(strict_types=1);

final class DataRequest
{
    public const TYPES = [
        'acesso'   => 'Cópia dos meus dados',
        'correcao' => 'Correção de dados',
        'exclusao' => 'Exclusão da minha conta e dados',
    ];

    public const STATUSES = [
        'aberta'    => 'Aberta',
        'concluida' => 'Concluída',
        'recusada'  => 'Recusada',
    ];

    public static function create(int $userId, string $type, ?string $details): int
    {
        return Database::insert(
            'INSERT INTO data_requests (user_id, request_type, details) VALUES (:u, :t, :d)',
            ['u' => $userId, 't' => $type, 'd' => $details]
        );
    }

    public static function hasOpen(int $userId, string $type): bool
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM data_requests WHERE user_id = :u AND request_type = :t AND status = 'aberta'",
            ['u' => $userId, 't' => $type]
        ) > 0;
    }

    public static function forUser(int $userId): array
    {
        return Database::all('SELECT * FROM data_requests WHERE user_id = :u ORDER BY created_at DESC', ['u' => $userId]);
    }

    public static function all(string $status = ''): array
    {
        $sql = 'SELECT d.*, u.name AS user_name, u.email AS user_email, u.status AS user_status, r.name AS resolver_name
                  FROM data_requests d
             LEFT JOIN users u ON u.id = d.user_id
             LEFT JOIN users r ON r.id = d.resolved_by';
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE d.status = :s';
            $params['s'] = $status;
        }
        return Database::all($sql . ' ORDER BY d.status = "aberta" DESC, d.created_at DESC', $params);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM data_requests WHERE id = :id', ['id' => $id]);
    }

    public static function countOpen(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM data_requests WHERE status = 'aberta'");
    }

    public static function resolve(int $id, string $status, int $resolvedBy, ?string $notes): void
    {
        Database::run(
            'UPDATE data_requests SET status = :s, resolved_by = :by, resolved_at = NOW(), resolution_notes = :n WHERE id = :id',
            ['s' => $status, 'by' => $resolvedBy, 'n' => $notes, 'id' => $id]
        );
    }
}
