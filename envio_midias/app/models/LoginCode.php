<?php
// app/models/LoginCode.php — código de 6 dígitos por e-mail (2ª etapa) e aparelhos confiáveis
declare(strict_types=1);

final class LoginCode
{
    /** Gera um código novo (invalida os anteriores) e devolve o código em claro para o e-mail. */
    public static function issue(int $userId): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Database::run('UPDATE login_codes SET used_at = NOW() WHERE user_id = :u AND used_at IS NULL', ['u' => $userId]);
        Database::run(
            'INSERT INTO login_codes (user_id, code_hash, expires_at, ip) VALUES (:u, :h, DATE_ADD(NOW(), INTERVAL :m MINUTE), :ip)',
            ['u' => $userId, 'h' => self::hash($userId, $code), 'm' => TWO_FACTOR_CODE_MINUTES, 'ip' => client_ip()]
        );
        return $code;
    }

    private static function hash(int $userId, string $code): string
    {
        return hash_hmac('sha256', $userId . ':' . $code, APP_KEY !== '' ? APP_KEY : SESSION_NAME);
    }

    /** Confere o código; limita a 5 tentativas por código. */
    public static function verify(int $userId, string $code): bool
    {
        $row = Database::one('SELECT * FROM login_codes WHERE user_id = :u AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1', ['u' => $userId]);
        if (!$row) {
            return false;
        }
        if ((int) $row['attempts'] >= 5) {
            Database::run('UPDATE login_codes SET used_at = NOW() WHERE id = :id', ['id' => $row['id']]);
            return false;
        }
        if (!hash_equals($row['code_hash'], self::hash($userId, preg_replace('/\D/', '', $code) ?? ''))) {
            Database::run('UPDATE login_codes SET attempts = attempts + 1 WHERE id = :id', ['id' => $row['id']]);
            return false;
        }
        Database::run('UPDATE login_codes SET used_at = NOW() WHERE id = :id', ['id' => $row['id']]);
        return true;
    }

    public static function recentCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM login_codes WHERE user_id = :u AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', ['u' => $userId]);
    }

    // ---- Aparelhos confiáveis (cookie separado, só para pular a 2ª etapa) ----

    public static function trustDevice(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        Database::run(
            'INSERT INTO trusted_devices (user_id, token_hash, label, ip, expires_at) VALUES (:u, :h, :l, :ip, DATE_ADD(NOW(), INTERVAL :d DAY))',
            ['u' => $userId, 'h' => hash('sha256', $token), 'l' => mb_substr(user_agent(), 0, 120), 'ip' => client_ip(), 'd' => TRUSTED_DEVICE_DAYS]
        );
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(BASE_URL, 'https://');
        setcookie(SESSION_NAME . '_TD', $token, ['expires' => time() + TRUSTED_DEVICE_DAYS * 86400, 'path' => BASE_PATH ?: '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    }

    public static function deviceTrusted(int $userId): bool
    {
        $token = $_COOKIE[SESSION_NAME . '_TD'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $row = Database::one('SELECT id FROM trusted_devices WHERE user_id = :u AND token_hash = :h AND expires_at > NOW()', ['u' => $userId, 'h' => hash('sha256', $token)]);
        if ($row) {
            Database::run('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = :id', ['id' => $row['id']]);
        }
        return $row !== null;
    }

    public static function forgetDevices(int $userId): void
    {
        Database::run('DELETE FROM trusted_devices WHERE user_id = :u', ['u' => $userId]);
    }

    public static function purge(): void
    {
        Database::run('DELETE FROM login_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        Database::run('DELETE FROM trusted_devices WHERE expires_at < NOW()');
    }
}
