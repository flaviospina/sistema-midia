<?php
// app/models/PasswordReset.php — tokens de redefinição de senha (uso único, com validade)
declare(strict_types=1);

final class PasswordReset
{
    /** Cria um token novo (invalidando os anteriores do usuário) e devolve o token em claro para o e-mail. */
    public static function issue(int $userId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Database::run('UPDATE password_resets SET used_at = NOW() WHERE user_id = :u AND used_at IS NULL', ['u' => $userId]);
        Database::run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, ip) VALUES (:u, :h, DATE_ADD(NOW(), INTERVAL :m MINUTE), :ip)',
            ['u' => $userId, 'h' => hash('sha256', $token), 'm' => PASSWORD_RESET_MINUTES, 'ip' => client_ip()]
        );
        return $token;
    }

    /** Token válido (existe, não usado, não expirado) ou null. */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/', $token)) {
            return null;
        }
        return Database::one(
            'SELECT r.*, u.email, u.name, u.status FROM password_resets r JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = :h AND r.used_at IS NULL AND r.expires_at > NOW()',
            ['h' => hash('sha256', $token)]
        );
    }

    public static function consume(int $id): void
    {
        Database::run('UPDATE password_resets SET used_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    /** Pedidos recentes por usuário (limite anti-abuso). */
    public static function recentCount(int $userId, int $minutes = 60): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM password_resets WHERE user_id = :u AND created_at > DATE_SUB(NOW(), INTERVAL :m MINUTE)', ['u' => $userId, 'm' => $minutes]);
    }

    public static function purge(): int
    {
        return Database::run('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')->rowCount();
    }
}
