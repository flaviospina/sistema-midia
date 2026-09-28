<?php
// app/models/SwapRequest.php — troca de escala entre membros (com aprovação do coordenador)
declare(strict_types=1);

final class SwapRequest
{
    public const STATUSES = [
        'aguardando_membro'      => 'Aguardando o colega aceitar',
        'aguardando_coordenador' => 'Aguardando aprovação do coordenador',
        'aprovada'               => 'Aprovada',
        'recusada_membro'        => 'Recusada pelo colega',
        'rejeitada'              => 'Rejeitada pelo coordenador',
        'cancelada'              => 'Cancelada',
    ];

    private const SELECT = 'SELECT s.*, a.event_id, a.function_id, a.user_id AS current_user_id,
                                   uf.name AS from_name, ut.name AS to_name, f.name AS function_name,
                                   e.title AS event_title, e.starts_at, d.name AS decided_name
                              FROM swap_requests s
                              JOIN assignments a ON a.id = s.assignment_id
                              JOIN users uf ON uf.id = s.from_user_id
                              JOIN users ut ON ut.id = s.to_user_id
                              JOIN media_functions f ON f.id = a.function_id
                              JOIN events e ON e.id = a.event_id
                         LEFT JOIN users d ON d.id = s.decided_by';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE s.id = :id', ['id' => $id]);
    }

    public static function create(int $assignmentId, int $fromUserId, int $toUserId, ?string $reason): int
    {
        return Database::insert(
            'INSERT INTO swap_requests (assignment_id, from_user_id, to_user_id, reason) VALUES (:a, :f, :t, :r)',
            ['a' => $assignmentId, 'f' => $fromUserId, 't' => $toUserId, 'r' => $reason]
        );
    }

    public static function openForAssignment(int $assignmentId): ?array
    {
        return Database::one(self::SELECT . " WHERE s.assignment_id = :a AND s.status IN ('aguardando_membro','aguardando_coordenador') ORDER BY s.id DESC LIMIT 1", ['a' => $assignmentId]);
    }

    /** Pedidos em que a pessoa é o colega convidado ou o solicitante. */
    public static function forUser(int $userId): array
    {
        return Database::all(self::SELECT . " WHERE (s.to_user_id = :u1 OR s.from_user_id = :u2) AND e.starts_at >= NOW() ORDER BY s.status, e.starts_at", ['u1' => $userId, 'u2' => $userId]);
    }

    public static function pendingForCoordinator(): array
    {
        return Database::all(self::SELECT . " WHERE s.status = 'aguardando_coordenador' AND e.starts_at >= NOW() ORDER BY e.starts_at");
    }

    public static function setStatus(int $id, string $status, ?int $decidedBy = null): void
    {
        Database::run(
            'UPDATE swap_requests SET status = :s, decided_by = :by, decided_at = CASE WHEN :by2 IS NULL THEN decided_at ELSE NOW() END WHERE id = :id',
            ['s' => $status, 'by' => $decidedBy, 'by2' => $decidedBy, 'id' => $id]
        );
    }
}
