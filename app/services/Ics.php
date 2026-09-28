<?php
// app/services/Ics.php — exportação iCalendar da escala pessoal
declare(strict_types=1);

final class Ics
{
    public static function tokenFor(int $userId): string
    {
        $token = Database::value('SELECT token FROM calendar_tokens WHERE user_id = :u', ['u' => $userId]);
        if ($token === null) {
            $token = rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
            Database::run('INSERT INTO calendar_tokens (user_id, token) VALUES (:u, :t)', ['u' => $userId, 't' => $token]);
        }
        return (string) $token;
    }

    public static function regenerate(int $userId): string
    {
        Database::run('DELETE FROM calendar_tokens WHERE user_id = :u', ['u' => $userId]);
        return self::tokenFor($userId);
    }

    public static function userIdByToken(string $token): ?int
    {
        $id = Database::value('SELECT user_id FROM calendar_tokens WHERE token = :t', ['t' => $token]);
        return $id === null ? null : (int) $id;
    }

    /** Gera o calendário (assignments não recusados) de -30 a +180 dias. */
    public static function build(int $userId, string $userName): string
    {
        $rows = Assignment::forUser($userId, 30, 180);
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ADMoema//Central de Midia//PT',
            'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::esc('Escala Mídia — ' . $userName),
            'X-WR-TIMEZONE:America/Sao_Paulo',
        ];
        foreach ($rows as $a) {
            if ($a['status'] === 'recusado') {
                continue;
            }
            $start = new DateTime($a['starts_at']);
            $end = new DateTime($a['ends_at'] ?: $a['starts_at'] . ' +2 hours');
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:escala-' . $a['id'] . '@admoema-midia';
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART;TZID=America/Sao_Paulo:' . $start->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=America/Sao_Paulo:' . $end->format('Ymd\THis');
            $lines[] = 'SUMMARY:' . self::esc('[Mídia] ' . $a['event_title'] . ' — ' . $a['function_name']);
            $desc = 'Função: ' . $a['function_name'] . '\nSituação: ' . (Assignment::STATUSES[$a['status']] ?? $a['status']);
            $lines[] = 'DESCRIPTION:' . self::esc($desc);
            if ($a['location']) {
                $lines[] = 'LOCATION:' . self::esc($a['location']);
            }
            $lines[] = 'STATUS:' . ($a['status'] === 'confirmado' ? 'CONFIRMED' : 'TENTATIVE');
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s);
    }

    /** Quebra linhas com mais de 75 bytes (RFC 5545). */
    private static function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 75) {
            $out .= substr($line, 0, 75) . "\r\n ";
            $line = substr($line, 75);
        }
        return $out . $line;
    }
}
