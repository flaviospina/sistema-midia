<?php
// app/models/User.php — usuários do Envio de Mídias (tabela própria em_users, com o perfil na própria linha)
declare(strict_types=1);

final class User
{
    public const DEFAULT_PASSWORD = 'ADMoema2026@1'; // senha do primeiro acesso: troca obrigatória antes de usar o sistema

    private const BASE = 'SELECT u.* FROM em_users u';

    public static function findForAuth(int $id): ?array
    {
        return Database::one(self::BASE . ' WHERE u.id = :id', ['id' => $id]);
    }

    public static function find(int $id): ?array
    {
        return self::findForAuth($id);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one(self::BASE . ' WHERE u.email = :email', ['email' => mb_strtolower($email)]);
    }

    /** Lista para a administração. */
    public static function all(): array
    {
        return Database::all(
            'SELECT u.id, u.name, u.email, u.role, u.status, u.must_change_password, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM em_photos p WHERE p.uploaded_by = u.id) AS photos
               FROM em_users u ORDER BY u.name'
        );
    }

    /** Cria o usuário com a senha padrão do primeiro acesso (DEFAULT_PASSWORD) e troca obrigatória. */
    public static function create(string $name, string $email, string $role): int
    {
        return Database::insert(
            'INSERT INTO em_users (name, email, password_hash, role, must_change_password, status) VALUES (:n, :e, :h, :r, 1, :s)',
            ['n' => $name, 'e' => $email, 'h' => self::hash(self::DEFAULT_PASSWORD), 'r' => $role, 's' => 'ativo']
        );
    }

    public static function setRole(int $id, string $role): void
    {
        Database::run('UPDATE em_users SET role = :r WHERE id = :id', ['r' => $role, 'id' => $id]);
    }

    /** Exclui o usuário (as fotos enviadas por ele permanecem, sem autor). */
    public static function delete(int $id): void
    {
        Database::run('DELETE FROM em_users WHERE id = :id', ['id' => $id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE em_users SET status = :s, session_version = session_version + 1 WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    public static function update(int $id, string $name, string $email): void
    {
        Database::run('UPDATE em_users SET name = :n, email = :e WHERE id = :id', ['n' => $name, 'e' => $email, 'id' => $id]);
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
        Database::run('UPDATE em_users SET password_hash = :h, must_change_password = :m, session_version = session_version + 1 WHERE id = :id', ['h' => $hash, 'm' => $mustChange ? 1 : 0, 'id' => $id]);
    }

    /** Volta para a senha padrão do primeiro acesso (feito pelo administrador). */
    public static function resetToDefault(int $id): void
    {
        self::setPassword($id, self::hash(self::DEFAULT_PASSWORD), true);
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE em_users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM em_users WHERE email = :e' . ($exceptId ? ' AND id <> :id' : ''), $exceptId ? ['e' => $email, 'id' => $exceptId] : ['e' => $email]);
    }

    // ---- Preferências pessoais (2ª etapa, etc.) ------------------------

    public static function pref(int $userId, string $key, ?string $default = null): ?string
    {
        $v = Database::value('SELECT value FROM em_user_prefs WHERE user_id = :u AND pref_key = :k', ['u' => $userId, 'k' => $key]);
        return $v === null ? $default : (string) $v;
    }

    public static function setPref(int $userId, string $key, ?string $value): void
    {
        if ($value === null) {
            Database::run('DELETE FROM em_user_prefs WHERE user_id = :u AND pref_key = :k', ['u' => $userId, 'k' => $key]);
            return;
        }
        Database::run('INSERT INTO em_user_prefs (user_id, pref_key, value) VALUES (:u, :k, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)', ['u' => $userId, 'k' => $key, 'v' => $value]);
    }
}
