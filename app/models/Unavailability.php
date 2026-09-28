<?php
// app/models/Unavailability.php — indisponibilidades do membro (data única/período ou recorrente)
declare(strict_types=1);

final class Unavailability
{
    public static function forUser(int $userId, bool $onlyCurrent = true): array
    {
        $sql = 'SELECT * FROM unavailability WHERE user_id = :u';
        if ($onlyCurrent) {
            $sql .= " AND (kind = 'recorrente' OR COALESCE(date_to, date_from) >= CURDATE())";
        }
        return Database::all($sql . ' ORDER BY kind, date_from, weekday', ['u' => $userId]);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM unavailability WHERE id = :id', ['id' => $id]);
    }

    public static function create(int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO unavailability (user_id, kind, date_from, date_to, weekday, time_from, time_to, reason)
             VALUES (:u, :kind, :date_from, :date_to, :weekday, :time_from, :time_to, :reason)',
            $d + ['u' => $userId]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM unavailability WHERE id = :id', ['id' => $id]);
    }

    /** Todas as indisponibilidades vigentes da equipe (visão do coordenador). */
    public static function team(): array
    {
        return Database::all(
            "SELECT un.*, u.name AS user_name FROM unavailability un JOIN users u ON u.id = un.user_id
              WHERE u.status = 'ativo' AND (un.kind = 'recorrente' OR COALESCE(un.date_to, un.date_from) >= CURDATE())
              ORDER BY u.name, un.kind, un.date_from"
        );
    }

    /**
     * IDs das pessoas indisponíveis no horário do evento.
     */
    public static function unavailableUserIds(string $startsAt, ?string $endsAt): array
    {
        $start = new DateTime($startsAt);
        $end = new DateTime($endsAt ?: $startsAt . ' +2 hours');
        $date = $start->format('Y-m-d');
        $weekday = (int) $start->format('w');
        $rows = Database::all(
            "SELECT user_id, kind, time_from, time_to FROM unavailability
              WHERE (kind = 'data' AND date_from <= :d1 AND COALESCE(date_to, date_from) >= :d2)
                 OR (kind = 'recorrente' AND weekday = :w AND (date_from IS NULL OR date_from <= :d3) AND (date_to IS NULL OR date_to >= :d4))",
            ['d1' => $date, 'd2' => $date, 'w' => $weekday, 'd3' => $date, 'd4' => $date]
        );
        $ids = [];
        foreach ($rows as $r) {
            if ($r['time_from'] && $r['time_to']) {
                // Só parte do dia: verifica sobreposição com o horário do evento
                $tf = new DateTime($date . ' ' . $r['time_from']);
                $tt = new DateTime($date . ' ' . $r['time_to']);
                if ($tt <= $start || $tf >= $end) {
                    continue;
                }
            }
            $ids[] = (int) $r['user_id'];
        }
        return array_values(array_unique($ids));
    }

    public static function describe(array $u): string
    {
        if ($u['kind'] === 'recorrente') {
            $s = 'Toda ' . (Event::WEEKDAYS[(int) $u['weekday']] ?? '');
            if ($u['time_from'] && $u['time_to']) {
                $s .= ' das ' . substr($u['time_from'], 0, 5) . ' às ' . substr($u['time_to'], 0, 5);
            }
            if ($u['date_to']) {
                $s .= ' até ' . format_date($u['date_to']);
            }
            return $s;
        }
        $s = format_date($u['date_from']);
        if ($u['date_to'] && $u['date_to'] !== $u['date_from']) {
            $s .= ' a ' . format_date($u['date_to']);
        }
        if ($u['time_from'] && $u['time_to']) {
            $s .= ' das ' . substr($u['time_from'], 0, 5) . ' às ' . substr($u['time_to'], 0, 5);
        }
        return $s;
    }
}
