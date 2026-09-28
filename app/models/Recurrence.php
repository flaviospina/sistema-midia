<?php
// app/models/Recurrence.php — cultos fixos por recorrência (geram eventos automaticamente)
declare(strict_types=1);

final class Recurrence
{
    public const FREQUENCIES = [
        'semanal'   => 'Toda semana',
        'quinzenal' => 'A cada duas semanas',
        'mensal'    => 'Uma vez por mês',
    ];

    public const WEEKS_OF_MONTH = [1 => '1ª', 2 => '2ª', 3 => '3ª', 4 => '4ª', -1 => 'Última'];

    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT r.*, t.name AS template_name,
                       (SELECT COUNT(*) FROM events e WHERE e.recurrence_id = r.id AND e.starts_at >= NOW()) AS future_events
                  FROM event_recurrences r LEFT JOIN schedule_templates t ON t.id = r.template_id';
        if ($onlyActive) {
            $sql .= ' WHERE r.active = 1';
        }
        return Database::all($sql . ' ORDER BY r.weekday, r.start_time');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM event_recurrences WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO event_recurrences (title, event_type, frequency, weekday, week_of_month, start_time, duration_minutes, location, template_id, starts_on, ends_on, active, created_by)
             VALUES (:title, :event_type, :frequency, :weekday, :week_of_month, :start_time, :duration_minutes, :location, :template_id, :starts_on, :ends_on, :active, :by)',
            $d + ['by' => Auth::id()]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE event_recurrences SET title = :title, event_type = :event_type, frequency = :frequency, weekday = :weekday, week_of_month = :week_of_month,
                    start_time = :start_time, duration_minutes = :duration_minutes, location = :location, template_id = :template_id,
                    starts_on = :starts_on, ends_on = :ends_on, active = :active WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function describe(array $r): string
    {
        $day = Event::WEEKDAYS[(int) $r['weekday']] ?? '';
        $when = match ($r['frequency']) {
            'quinzenal' => "{$day}, a cada duas semanas",
            'mensal'    => (self::WEEKS_OF_MONTH[(int) $r['week_of_month']] ?? '') . " {$day} do mês",
            default     => "Toda {$day}",
        };
        return $when . ' às ' . substr((string) $r['start_time'], 0, 5);
    }

    /**
     * Datas (Y-m-d) em que a recorrência ocorre entre $from e $to (inclusive).
     */
    public static function occurrences(array $r, string $from, string $to): array
    {
        $out = [];
        $start = new DateTime(max($from, $r['starts_on']));
        $limit = new DateTime($r['ends_on'] ? min($to, $r['ends_on']) : $to);
        $weekday = (int) $r['weekday'];
        $anchor = new DateTime($r['starts_on']);

        // Primeiro dia da semana desejada a partir de $start
        $d = clone $start;
        while ((int) $d->format('w') !== $weekday) {
            $d->modify('+1 day');
        }
        while ($d <= $limit) {
            $ok = match ($r['frequency']) {
                'quinzenal' => intdiv((int) $anchor->diff($d)->days, 7) % 2 === 0,
                'mensal'    => self::isWeekOfMonth($d, (int) $r['week_of_month']),
                default     => true,
            };
            if ($ok) {
                $out[] = $d->format('Y-m-d');
            }
            $d->modify('+7 days');
        }
        return $out;
    }

    private static function isWeekOfMonth(DateTime $d, int $week): bool
    {
        $dom = (int) $d->format('j');
        if ($week === -1) {
            $daysInMonth = (int) $d->format('t');
            return $dom > $daysInMonth - 7;
        }
        return intdiv($dom - 1, 7) + 1 === $week;
    }

    /**
     * Gera os eventos das recorrências ativas até SCHEDULE_WEEKS_AHEAD semanas à frente.
     * Idempotente (índice único recurrence_id + starts_at). Devolve quantos foram criados.
     */
    public static function generate(?int $onlyId = null): int
    {
        $created = 0;
        $from = date('Y-m-d');
        $to = date('Y-m-d', strtotime('+' . SCHEDULE_WEEKS_AHEAD . ' weeks'));
        foreach (self::all(true) as $r) {
            if ($onlyId !== null && (int) $r['id'] !== $onlyId) {
                continue;
            }
            foreach (self::occurrences($r, $from, $to) as $date) {
                $startsAt = $date . ' ' . substr((string) $r['start_time'], 0, 8);
                $exists = Database::value('SELECT id FROM events WHERE recurrence_id = :r AND starts_at = :s', ['r' => $r['id'], 's' => $startsAt]);
                if ($exists) {
                    continue;
                }
                $id = Database::insert(
                    'INSERT INTO events (title, event_type, starts_at, ends_at, location, recurrence_id, created_by)
                     VALUES (:title, :type, :starts, DATE_ADD(:starts2, INTERVAL :dur MINUTE), :loc, :rec, :by)',
                    ['title' => $r['title'], 'type' => $r['event_type'], 'starts' => $startsAt, 'starts2' => $startsAt,
                     'dur' => (int) $r['duration_minutes'], 'loc' => $r['location'], 'rec' => $r['id'], 'by' => $r['created_by']]
                );
                if ($r['template_id']) {
                    Event::applyTemplate($id, (int) $r['template_id']);
                }
                $created++;
            }
        }
        return $created;
    }

    /** Remove eventos futuros ainda sem escala de uma recorrência desativada/alterada. */
    public static function pruneFuture(int $id): int
    {
        return Database::run(
            'DELETE FROM events WHERE recurrence_id = :r AND starts_at > NOW()
                AND NOT EXISTS (SELECT 1 FROM assignments a WHERE a.event_id = events.id)',
            ['r' => $id]
        )->rowCount();
    }
}
