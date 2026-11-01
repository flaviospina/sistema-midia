<?php
// app/models/RateLimit.php — limite de tentativas por chave (login, cadastro)
declare(strict_types=1);

final class RateLimit
{
    /** Quantas ocorrências da chave nos últimos $minutes minutos. */
    public static function count(string $bucket, string $key, int $minutes): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM rate_limit_hits
              WHERE bucket = :b AND rate_key = :k AND hit_at > DATE_SUB(NOW(), INTERVAL :m MINUTE)',
            ['b' => $bucket, 'k' => $key, 'm' => $minutes]
        );
    }

    public static function hit(string $bucket, string $key): void
    {
        Database::run('INSERT INTO rate_limit_hits (bucket, rate_key) VALUES (:b, :k)', ['b' => $bucket, 'k' => $key]);
        // Limpeza oportunista (1 em 50 chamadas) — não depende do cron
        if (random_int(1, 50) === 1) {
            Database::run('DELETE FROM rate_limit_hits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
    }

    public static function clear(string $bucket, string $key): void
    {
        Database::run('DELETE FROM rate_limit_hits WHERE bucket = :b AND rate_key = :k', ['b' => $bucket, 'k' => $key]);
    }
}
