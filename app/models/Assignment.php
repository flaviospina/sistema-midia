<?php
// app/models/Assignment.php — escalas (pessoa × função × evento)
declare(strict_types=1);

final class Assignment
{
    public const STATUSES = [
        'pendente'   => 'Aguardando confirmação',
        'confirmado' => 'Confirmado',
        'recusado'   => 'Recusado',
    ];

    private const SELECT = 'SELECT a.*, u.name AS user_name, u.whatsapp AS user_whatsapp, u.photo_path, f.name AS function_name, f.sort_order,
                                   e.title AS event_title, e.starts_at, e.ends_at, e.location, e.status AS event_status, e.event_type,
                                   mf.level
                              FROM assignments a
                              JOIN users u ON u.id = a.user_id
                              JOIN media_functions f ON f.id = a.function_id
                              JOIN events e ON e.id = a.event_id
                         LEFT JOIN member_functions mf ON mf.user_id = a.user_id AND mf.function_id = a.function_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE a.id = :id', ['id' => $id]);
    }

    /** Escala completa de um evento, agrupada por função. */
    public static function forEvent(int $eventId): array
    {
        $rows = Database::all(self::SELECT . ' WHERE a.event_id = :e ORDER BY f.sort_order, a.status, u.name', ['e' => $eventId]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['function_id']][] = $r;
        }
        return $out;
    }

    /** Próximas escalas de uma pessoa. */
    public static function forUser(int $userId, int $pastDays = 0, int $futureDays = 120): array
    {
        return Database::all(
            self::SELECT . " WHERE a.user_id = :u AND e.status <> 'cancelado'
                AND e.starts_at >= DATE_SUB(NOW(), INTERVAL :p DAY) AND e.starts_at < DATE_ADD(NOW(), INTERVAL :f DAY)
              ORDER BY e.starts_at",
            ['u' => $userId, 'p' => $pastDays, 'f' => $futureDays]
        );
    }

    public static function pendingForUser(int $userId): array
    {
        return Database::all(
            self::SELECT . " WHERE a.user_id = :u AND a.status = 'pendente' AND e.status = 'agendado' AND e.starts_at >= NOW() ORDER BY e.starts_at",
            ['u' => $userId]
        );
    }

    public static function create(int $eventId, int $functionId, int $userId, string $status = 'pendente'): int
    {
        return Database::insert(
            'INSERT INTO assignments (event_id, function_id, user_id, status, assigned_by) VALUES (:e, :f, :u, :s, :by)',
            ['e' => $eventId, 'f' => $functionId, 'u' => $userId, 's' => $status, 'by' => Auth::id()]
        );
    }

    public static function exists(int $eventId, int $functionId, int $userId): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM assignments WHERE event_id = :e AND function_id = :f AND user_id = :u', ['e' => $eventId, 'f' => $functionId, 'u' => $userId]) > 0;
    }

    public static function respond(int $id, string $status, ?string $note): void
    {
        Database::run(
            'UPDATE assignments SET status = :s, note = :n, responded_at = NOW() WHERE id = :id',
            ['s' => $status, 'n' => $note, 'id' => $id]
        );
    }

    public static function reassign(int $id, int $newUserId): void
    {
        Database::run(
            "UPDATE assignments SET user_id = :u, status = 'pendente', responded_at = NULL, note = NULL WHERE id = :id",
            ['u' => $newUserId, 'id' => $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM assignments WHERE id = :id', ['id' => $id]);
    }

    /** IDs de pessoas já escaladas no evento (qualquer função, não recusadas). */
    public static function userIdsInEvent(int $eventId): array
    {
        return array_map('intval', array_column(Database::all("SELECT user_id FROM assignments WHERE event_id = :e AND status <> 'recusado'", ['e' => $eventId]), 'user_id'));
    }

    /**
     * Estatísticas de rodízio por pessoa: escalas nos últimos N dias e data da última escala.
     * @return array user_id => ['recent' => int, 'last' => ?string, 'month' => int]
     */
    public static function rotationStats(int $days): array
    {
        $out = [];
        foreach (Database::all(
            "SELECT a.user_id,
                    SUM(e.starts_at >= DATE_SUB(NOW(), INTERVAL :d DAY) AND e.starts_at <= NOW()) AS recent,
                    SUM(e.starts_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND e.starts_at < DATE_ADD(NOW(), INTERVAL 30 DAY)) AS month,
                    MAX(CASE WHEN e.starts_at <= NOW() THEN e.starts_at END) AS last_at
               FROM assignments a JOIN events e ON e.id = a.event_id
              WHERE a.status <> 'recusado' AND e.status <> 'cancelado'
           GROUP BY a.user_id",
            ['d' => $days]
        ) as $r) {
            $out[(int) $r['user_id']] = ['recent' => (int) $r['recent'], 'month' => (int) $r['month'], 'last' => $r['last_at']];
        }
        return $out;
    }

    /** Escalas da pessoa que colidem no horário com o evento. */
    public static function conflictsForUser(int $userId, array $event): array
    {
        $start = $event['starts_at'];
        $end = $event['ends_at'] ?: date('Y-m-d H:i:s', strtotime($start . ' +2 hours'));
        return Database::all(
            "SELECT e.id, e.title, e.starts_at FROM assignments a JOIN events e ON e.id = a.event_id
              WHERE a.user_id = :u AND a.status <> 'recusado' AND e.status = 'agendado' AND e.id <> :ev
                AND e.starts_at < :end AND COALESCE(e.ends_at, DATE_ADD(e.starts_at, INTERVAL 2 HOUR)) > :start",
            ['u' => $userId, 'ev' => $event['id'], 'start' => $start, 'end' => $end]
        );
    }

    /** Resumo para o painel do coordenador: eventos próximos com vagas abertas ou pendências. */
    public static function openSlotsSummary(int $days = 21): array
    {
        return Database::all(
            "SELECT e.id, e.title, e.starts_at,
                    COALESCE((SELECT SUM(quantity) FROM event_slots s WHERE s.event_id = e.id),0) AS slots_total,
                    (SELECT COUNT(*) FROM assignments a WHERE a.event_id = e.id AND a.status <> 'recusado') AS assigned_total,
                    (SELECT COUNT(*) FROM assignments a WHERE a.event_id = e.id AND a.status = 'pendente') AS pending_total,
                    (SELECT COUNT(*) FROM assignments a WHERE a.event_id = e.id AND a.status = 'recusado') AS declined_total
               FROM events e
              WHERE e.status = 'agendado' AND e.starts_at >= NOW() AND e.starts_at < DATE_ADD(NOW(), INTERVAL :d DAY)
              ORDER BY e.starts_at",
            ['d' => $days]
        );
    }

    /** Frequência por pessoa num período (painel). */
    public static function statsByUser(string $from, string $to): array
    {
        return Database::all(
            "SELECT u.id, u.name,
                    SUM(a.status = 'confirmado') AS confirmados,
                    SUM(a.status = 'pendente') AS pendentes,
                    SUM(a.status = 'recusado') AS recusados,
                    COUNT(*) AS total
               FROM assignments a JOIN users u ON u.id = a.user_id JOIN events e ON e.id = a.event_id
              WHERE e.starts_at >= :from AND e.starts_at < :to AND e.status <> 'cancelado'
           GROUP BY u.id, u.name ORDER BY total DESC, u.name",
            ['from' => $from, 'to' => $to]
        );
    }
}
