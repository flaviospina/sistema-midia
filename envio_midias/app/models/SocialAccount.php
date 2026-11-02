<?php
// app/models/SocialAccount.php — contas de rede social de cada pessoa (token cifrado) e histórico de publicações
declare(strict_types=1);

final class SocialAccount
{
    public const NETWORKS = ['instagram' => 'Instagram', 'facebook' => 'Página do Facebook'];

    public static function forUser(int $userId): array
    {
        $out = [];
        foreach (Database::all('SELECT * FROM social_accounts WHERE user_id = :u', ['u' => $userId]) as $r) {
            $out[$r['network']] = $r;
        }
        return $out;
    }

    public static function get(int $userId, string $network): ?array
    {
        return Database::one('SELECT * FROM social_accounts WHERE user_id = :u AND network = :n', ['u' => $userId, 'n' => $network]);
    }

    public static function save(int $userId, string $network, string $accountId, ?string $accountName, string $token, ?string $expiresAt): void
    {
        Database::run(
            'INSERT INTO social_accounts (user_id, network, account_id, account_name, token_enc, token_expires_at) VALUES (:u, :n, :a, :an, :t, :e)
             ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), account_name = VALUES(account_name), token_enc = VALUES(token_enc), token_expires_at = VALUES(token_expires_at)',
            ['u' => $userId, 'n' => $network, 'a' => $accountId, 'an' => $accountName, 't' => Crypto::encrypt($token), 'e' => $expiresAt]
        );
    }

    public static function remove(int $userId, string $network): void
    {
        Database::run('DELETE FROM social_accounts WHERE user_id = :u AND network = :n', ['u' => $userId, 'n' => $network]);
    }

    public static function token(array $account): ?string
    {
        return Crypto::decrypt($account['token_enc']);
    }

    public static function logPost(int $photoId, string $network, ?string $caption, bool $ok, ?string $externalId, ?string $permalink, ?string $error): int
    {
        return Database::insert(
            'INSERT INTO social_posts (photo_id, user_id, network, caption, status, external_id, permalink, error) VALUES (:p, :u, :n, :c, :s, :e, :l, :err)',
            ['p' => $photoId, 'u' => Auth::id(), 'n' => $network, 'c' => $caption, 's' => $ok ? 'publicado' : 'falhou', 'e' => $externalId, 'l' => $permalink, 'err' => $error ? mb_substr($error, 0, 500) : null]
        );
    }

    public static function postsFor(int $photoId): array
    {
        return Database::all('SELECT s.*, u.name AS user_name FROM social_posts s JOIN users u ON u.id = s.user_id WHERE s.photo_id = :p ORDER BY s.id DESC', ['p' => $photoId]);
    }
}
