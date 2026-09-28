<?php
// app/models/Event.php — cultos e eventos
declare(strict_types=1);

final class Event
{
    public const TYPES = [
        'culto'    => 'Culto',
        'especial' => 'Evento especial',
        'ensaio'   => 'Ensaio',
        'reuniao'  => 'Reunião',
        'outro'    => 'Outro',
    ];

    public const STATUSES = [
        'agendado'  => 'Agendado',
        'cancelado' => 'Cancelado',
        'concluido' => 'Concluído',
    ];

    public const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    private const SELECT = 'SELECT e.*, m.name AS ministry_name, r.title AS recurrence_title,
                                   (SELECT COALESCE(SUM(s.quantity),0) FROM event_slots s WHERE s.event_id = e.id) AS slots_total,
                                   (SELECT COUNT(*) FROM assignments a WHERE a.event_id = e.id AND a.status <> "recusado") AS assigned_total,
                                   (SELECT COUNT(*) FROM assignments a WHERE a.event_id = e.id AND a.status = "confirmado") AS confirmed_total
                              FROM events e
                         LEFT JOIN ministries m ON m.id = e.ministry_id
                         LEFT JOIN event_recurrences r ON r.id = e.recurrence_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE e.id = :id', ['id' => $id]);
    }

    /** Eventos de um intervalo (para calendário e listas). */
    public static function between(string $from, string $to, string $type = '', bool $includeCancelled = true): array
    {
        $sql = self::SELECT . ' WHERE e.starts_at >= :from AND e.starts_at < :to';
        $params = ['from' => $from, 'to' => $to];
        if ($type !== '') {
            $sql .= ' AND e.event_type = :type';
            $params['type'] = $type;
        }
        if (!$includeCancelled) {
            $sql .= " AND e.status <> 'cancelado'";
        }
        return Database::all($sql . ' ORDER BY e.starts_at', $params);
    }

    public static function upcoming(int $days = 14, int $limit = 20): array
    {
        return Database::all(
            self::SELECT . " WHERE e.status = 'agendado' AND e.starts_at >= NOW() AND e.starts_at < DATE_ADD(NOW(), INTERVAL :d DAY) ORDER BY e.starts_at LIMIT :l",
            ['d' => $days, 'l' => $limit]
        );
    }

    /** Eventos recentes e próximos para selects (arquivos, /enviar). */
    public static function forSelect(int $pastDays = 60, int $futureDays = 60): array
    {
        return Database::all(
            "SELECT id, title, starts_at FROM events WHERE status <> 'cancelado'
              AND starts_at BETWEEN DATE_SUB(NOW(), INTERVAL :p DAY) AND DATE_ADD(NOW(), INTERVAL :f DAY)
            ORDER BY starts_at DESC",
            ['p' => $pastDays, 'f' => $futureDays]
        );
    }

    public static function label(array $event): string
    {
        return $event['title'] . ' · ' . format_date($event['starts_at'], 'd/m/Y H:i');
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO events (title, event_type, starts_at, ends_at, location, description, ministry_id, recurrence_id, notes, created_by)
             VALUES (:title, :event_type, :starts_at, :ends_at, :location, :description, :ministry_id, :recurrence_id, :notes, :created_by)',
            $d + ['recurrence_id' => null, 'created_by' => Auth::id()]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE events SET title = :title, event_type = :event_type, starts_at = :starts_at, ends_at = :ends_at, location = :location,
                    description = :description, ministry_id = :ministry_id, notes = :notes WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE events SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM events WHERE id = :id', ['id' => $id]);
    }

    /** Vagas por função do evento (com nome da função). */
    public static function slots(int $eventId): array
    {
        return Database::all(
            'SELECT s.*, f.name AS function_name, f.slug FROM event_slots s JOIN media_functions f ON f.id = s.function_id
              WHERE s.event_id = :e ORDER BY f.sort_order, f.name',
            ['e' => $eventId]
        );
    }

    /** Substitui as vagas: [function_id => quantity]. */
    public static function syncSlots(int $eventId, array $slots): void
    {
        Database::transaction(static function () use ($eventId, $slots): void {
            Database::run('DELETE FROM event_slots WHERE event_id = :e AND function_id NOT IN (' . (array_filter(array_keys($slots)) ? implode(',', array_map('intval', array_keys($slots))) : '0') . ')', ['e' => $eventId]);
            foreach ($slots as $fid => $qty) {
                $qty = max(0, min(20, (int) $qty));
                if ($qty === 0) {
                    Database::run('DELETE FROM event_slots WHERE event_id = :e AND function_id = :f', ['e' => $eventId, 'f' => (int) $fid]);
                    continue;
                }
                Database::run(
                    'INSERT INTO event_slots (event_id, function_id, quantity) VALUES (:e, :f, :q) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                    ['e' => $eventId, 'f' => (int) $fid, 'q' => $qty]
                );
            }
        });
    }

    public static function applyTemplate(int $eventId, int $templateId): void
    {
        $slots = [];
        foreach (ScheduleTemplate::slots($templateId) as $s) {
            $slots[(int) $s['function_id']] = (int) $s['quantity'];
        }
        if ($slots) {
            self::syncSlots($eventId, $slots);
        }
    }

    /** Eventos que se sobrepõem no tempo a este (para conflito de escala). */
    public static function overlapping(array $event): array
    {
        $start = $event['starts_at'];
        $end = $event['ends_at'] ?: date('Y-m-d H:i:s', strtotime($start . ' +2 hours'));
        return Database::all(
            "SELECT id, title, starts_at, ends_at FROM events
              WHERE id <> :id AND status = 'agendado'
                AND starts_at < :end AND COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 2 HOUR)) > :start",
            ['id' => $event['id'], 'start' => $start, 'end' => $end]
        );
    }

    /** Marca como concluídos os eventos já passados (cron). */
    public static function closePast(): int
    {
        return Database::run("UPDATE events SET status = 'concluido' WHERE status = 'agendado' AND COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 3 HOUR)) < NOW()")->rowCount();
    }

    /** Arquivos vinculados (event_id ou nome igual). */
    public static function files(int $eventId, string $title): array
    {
        [$where, $params] = Access::fileWhere();
        return MediaFile::attachTags(Database::all(
            "SELECT f.*, u.name AS uploader_name, NULL AS guest_name FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
              WHERE f.status = 'aprovado' AND (f.event_id = :e OR f.event_name = :t) AND {$where} ORDER BY f.created_at DESC LIMIT 60",
            $params + ['e' => $eventId, 't' => $title]
        ));
    }
}
