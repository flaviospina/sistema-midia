<?php
// app/models/User.php — usuários (tabela compartilhada com a Central de Mídia) e perfil neste sistema
declare(strict_types=1);

final class User
{
    public const DEFAULT_PASSWORD = 'ADMoema2026@1'; // senha do primeiro acesso: troca obrigatória antes de usar o sistema

    private const BASE = 'SELECT u.*, r.role FROM users u LEFT JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app';

    public static function findForAuth(int $id): ?array
    {
        return Database::one(self::BASE . ' WHERE u.id = :id', ['app' => APP_CODE, 'id' => $id]);
    }

    public static function find(int $id): ?array
    {
        return self::findForAuth($id);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one(self::BASE . ' WHERE u.email = :email', ['app' => APP_CODE, 'email' => mb_strtolower($email)]);
    }

    /** Lista para a administração (quem tem perfil neste sistema). */
    public static function all(): array
    {
        return Database::all(
            'SELECT u.id, u.name, u.email, u.status, u.must_change_password, u.last_login_at, u.created_at, r.role,
                    (SELECT COUNT(*) FROM photos p WHERE p.uploaded_by = u.id) AS photos
               FROM users u JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app ORDER BY u.name',
            ['app' => APP_CODE]
        );
    }

    /** Pessoas cadastradas na Central de Mídia (mesmo banco) ainda sem acesso às fotos. */
    public static function withoutRole(): array
    {
        return Database::all(
            "SELECT u.id, u.name, u.email FROM users u LEFT JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app
              WHERE r.id IS NULL AND u.status = 'ativo' ORDER BY u.name LIMIT 300",
            ['app' => APP_CODE]
        );
    }

    /**
     * Cria (ou reaproveita, se o e-mail já existir no banco compartilhado) o usuário e dá o perfil neste sistema.
     * Senha inicial: DEFAULT_PASSWORD, com troca obrigatória no primeiro acesso.
     */
    public static function create(string $name, string $email, string $role): int
    {
        return Database::transaction(static function () use ($name, $email, $role): int {
            $existing = Database::one('SELECT id FROM users WHERE email = :e', ['e' => $email]);
            if ($existing) {
                $id = (int) $existing['id'];
            } else {
                $id = Database::insert(
                    'INSERT INTO users (name, email, password_hash, must_change_password, status) VALUES (:n, :e, :h, 1, :s)',
                    ['n' => $name, 'e' => $email, 'h' => self::hash(self::DEFAULT_PASSWORD), 's' => 'ativo']
                );
            }
            Database::run('INSERT INTO user_roles (user_id, app_code, role) VALUES (:u, :app, :r) ON DUPLICATE KEY UPDATE role = VALUES(role)', ['u' => $id, 'app' => APP_CODE, 'r' => $role]);
            return $id;
        });
    }

    public static function setRole(int $id, string $role): void
    {
        Database::run('INSERT INTO user_roles (user_id, app_code, role) VALUES (:u, :app, :r) ON DUPLICATE KEY UPDATE role = VALUES(role)', ['u' => $id, 'app' => APP_CODE, 'r' => $role]);
    }

    public static function removeRole(int $id): void
    {
        Database::run('DELETE FROM user_roles WHERE user_id = :u AND app_code = :app', ['u' => $id, 'app' => APP_CODE]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE users SET status = :s, session_version = session_version + 1 WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    public static function update(int $id, string $name, string $email): void
    {
        Database::run('UPDATE users SET name = :n, email = :e WHERE id = :id', ['n' => $name, 'e' => $email, 'id' => $id]);
    }

    /** Argon2id quando disponível no servidor; senão bcrypt. */
    public static function hash(string $password): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
            : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function needsRehash(string $hash): bool
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
            : password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Define a senha; derruba as outras sessões (session_version). */
    public static function setPassword(int $id, string $hash, bool $mustChange): void
    {
        Database::run('UPDATE users SET password_hash = :h, must_change_password = :m, session_version = session_version + 1 WHERE id = :id', ['h' => $hash, 'm' => $mustChange ? 1 : 0, 'id' => $id]);
    }

    /** Volta para a senha padrão do primeiro acesso (feito pelo administrador). */
    public static function resetToDefault(int $id): void
    {
        self::setPassword($id, self::hash(self::DEFAULT_PASSWORD), true);
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM users WHERE email = :e' . ($exceptId ? ' AND id <> :id' : ''), $exceptId ? ['e' => $email, 'id' => $exceptId] : ['e' => $email]);
    }

    // ---- Preferências pessoais (2ª etapa, etc.) ------------------------

    public static function pref(int $userId, string $key, ?string $default = null): ?string
    {
        $v = Database::value('SELECT value FROM user_preferences WHERE user_id = :u AND pref_key = :k', ['u' => $userId, 'k' => $key]);
        return $v === null ? $default : (string) $v;
    }

    public static function setPref(int $userId, string $key, ?string $value): void
    {
        if ($value === null) {
            Database::run('DELETE FROM user_preferences WHERE user_id = :u AND pref_key = :k', ['u' => $userId, 'k' => $key]);
            return;
        }
        Database::run('INSERT INTO user_preferences (user_id, pref_key, value) VALUES (:u, :k, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)', ['u' => $userId, 'k' => $key, 'v' => $value]);
    }
}
