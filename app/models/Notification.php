<?php
// app/models/Notification.php — fila de avisos para o n8n
declare(strict_types=1);

final class Notification
{
    public const STATUSES = ['pendente' => 'Pendente', 'enviado' => 'Enviado', 'falhou' => 'Falhou', 'cancelado' => 'Cancelado'];
    public const MAX_ATTEMPTS = 6;

    public static function enqueue(string $event, array $recipients, string $message, array $data = [], ?string $dedupeKey = null, int $delaySeconds = 0): int
    {
        if ($dedupeKey !== null) {
            $existing = Database::one("SELECT id, data FROM notifications WHERE dedupe_key = :k AND status = 'pendente' ORDER BY id DESC LIMIT 1", ['k' => $dedupeKey]);
            if ($existing) {
                $old = json_decode((string) $existing['data'], true) ?: [];
                $data['count'] = (int) ($old['count'] ?? 1) + 1;
                $data['items'] = array_slice(array_merge($old['items'] ?? [], $data['items'] ?? []), -10);
                Database::run(
                    'UPDATE notifications SET recipients = :r, message = :m, data = :d WHERE id = :id',
                    ['r' => json_encode($recipients, JSON_UNESCAPED_UNICODE), 'm' => $message, 'd' => json_encode($data, JSON_UNESCAPED_UNICODE), 'id' => $existing['id']]
                );
                return (int) $existing['id'];
            }
            $data['count'] = $data['count'] ?? 1;
        }
        return Database::insert(
            'INSERT INTO notifications (event, dedupe_key, recipients, message, data, next_attempt_at)
             VALUES (:e, :k, :r, :m, :d, DATE_ADD(NOW(), INTERVAL :s SECOND))',
            ['e' => $event, 'k' => $dedupeKey, 'r' => json_encode($recipients, JSON_UNESCAPED_UNICODE), 'm' => $message,
             'd' => json_encode($data, JSON_UNESCAPED_UNICODE), 's' => max(0, $delaySeconds)]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM notifications WHERE id = :id', ['id' => $id]);
    }

    public static function due(int $limit = 30): array
    {
        return Database::all("SELECT * FROM notifications WHERE status = 'pendente' AND next_attempt_at <= NOW() ORDER BY id LIMIT :l", ['l' => $limit]);
    }

    public static function markSent(int $id, int $code): void
    {
        Database::run("UPDATE notifications SET status = 'enviado', sent_at = NOW(), response_code = :c, last_error = NULL, attempts = attempts + 1 WHERE id = :id", ['c' => $code, 'id' => $id]);
    }

    /** Falha: reagenda com espera crescente (1, 2, 4, 8, 16, 32 min); esgotou → falhou. */
    public static function markFailed(int $id, int $attempts, string $error, ?int $code): void
    {
        $attempts++;
        $status = $attempts >= self::MAX_ATTEMPTS ? 'falhou' : 'pendente';
        Database::run(
            "UPDATE notifications SET status = :s, attempts = :a, last_error = :e, response_code = :c,
                    next_attempt_at = DATE_ADD(NOW(), INTERVAL :m MINUTE) WHERE id = :id",
            ['s' => $status, 'a' => $attempts, 'e' => mb_substr($error, 0, 500), 'c' => $code, 'm' => 2 ** ($attempts - 1), 'id' => $id]
        );
    }

    public static function retry(int $id): void
    {
        Database::run("UPDATE notifications SET status = 'pendente', attempts = 0, next_attempt_at = NOW() WHERE id = :id", ['id' => $id]);
    }

    public static function cancel(int $id): void
    {
        Database::run("UPDATE notifications SET status = 'cancelado' WHERE id = :id AND status = 'pendente'", ['id' => $id]);
    }

    public static function recent(int $limit = 60): array
    {
        return Database::all('SELECT * FROM notifications ORDER BY id DESC LIMIT :l', ['l' => $limit]);
    }

    public static function stats(): array
    {
        $out = ['pendente' => 0, 'enviado' => 0, 'falhou' => 0, 'cancelado' => 0];
        foreach (Database::all('SELECT status, COUNT(*) AS n FROM notifications GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        $out['ultimo_envio'] = Database::value("SELECT MAX(sent_at) FROM notifications WHERE status = 'enviado'");
        return $out;
    }

    public static function purge(int $days = 60): int
    {
        return Database::run("DELETE FROM notifications WHERE status IN ('enviado','cancelado') AND created_at < DATE_SUB(NOW(), INTERVAL :d DAY)", ['d' => $days])->rowCount();
    }
}
