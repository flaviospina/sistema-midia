<?php
// app/models/User.php
declare(strict_types=1);

final class User
{
    private const BASE_SELECT = '
        SELECT u.*, r.role,
               m.member_status, m.joined_at, m.notes
          FROM users u
     LEFT JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app
     LEFT JOIN media_members m ON m.user_id = u.id';

    public static function findForAuth(int $id): ?array
    {
        return Database::one(self::BASE_SELECT . ' WHERE u.id = :id', ['app' => APP_CODE, 'id' => $id]);
    }

    public static function find(int $id): ?array
    {
        return self::findForAuth($id);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one(self::BASE_SELECT . ' WHERE u.email = :email', ['app' => APP_CODE, 'email' => mb_strtolower($email)]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => mb_strtolower($email)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    /**
     * Listagem paginada com filtros: q (nome/e-mail), role, status, member_status, function_id.
     */
    public static function search(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = ['app' => APP_CODE];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (($filters['role'] ?? '') !== '') {
            $where[] = 'r.role = :role';
            $params['role'] = $filters['role'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'u.status = :status';
            $params['status'] = $filters['status'];
        } else {
            $where[] = "u.status <> 'anonimizado'";
        }
        if (($filters['member_status'] ?? '') !== '') {
            $where[] = 'm.member_status = :member_status';
            $params['member_status'] = $filters['member_status'];
        }
        if (($filters['function_id'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM member_functions mf WHERE mf.user_id = u.id AND mf.function_id = :function_id)';
            $params['function_id'] = (int) $filters['function_id'];
        }

        $whereSql = implode(' AND ', $where);
        $from = ' FROM users u
             LEFT JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app
             LEFT JOIN media_members m ON m.user_id = u.id
            WHERE ' . $whereSql;

        $total = (int) Database::value('SELECT COUNT(*)' . $from, $params);
        $pg = paginate($total, $page, $perPage);

        $rows = Database::all(
            'SELECT u.id, u.name, u.email, u.whatsapp, u.photo_path, u.status, u.last_login_at,
                    r.role, m.member_status' . $from . '
             ORDER BY u.name
             LIMIT :limit OFFSET :offset',
            $params + ['limit' => $perPage, 'offset' => $pg['offset']]
        );

        // Funções de cada membro listado (uma consulta só)
        if ($rows) {
            $ids = array_column($rows, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            $funcs = Database::all(
                "SELECT mf.user_id, f.name, mf.level
                   FROM member_functions mf
                   JOIN media_functions f ON f.id = mf.function_id
                  WHERE mf.user_id IN ({$in})
               ORDER BY f.sort_order",
                $ids
            );
            $byUser = [];
            foreach ($funcs as $f) {
                $byUser[$f['user_id']][] = $f;
            }
            foreach ($rows as &$row) {
                $row['functions'] = $byUser[$row['id']] ?? [];
            }
            unset($row);
        }

        return ['itens' => $rows] + $pg;
    }

    public static function pending(): array
    {
        return Database::all(
            self::BASE_SELECT . " WHERE u.status = 'pendente' ORDER BY u.created_at",
            ['app' => APP_CODE]
        );
    }

    public static function countPending(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE status = 'pendente'");
    }

    public static function create(array $data, string $passwordHash, string $role, bool $mustChange, string $status = 'ativo'): int
    {
        return Database::transaction(static function () use ($data, $passwordHash, $role, $mustChange, $status): int {
            $id = Database::insert(
                'INSERT INTO users (name, email, whatsapp, password_hash, must_change_password, status)
                 VALUES (:name, :email, :whatsapp, :hash, :must, :status)',
                [
                    'name'     => $data['name'],
                    'email'    => mb_strtolower($data['email']),
                    'whatsapp' => $data['whatsapp'] ?: null,
                    'hash'     => $passwordHash,
                    'must'     => $mustChange ? 1 : 0,
                    'status'   => $status,
                ]
            );
            self::setRole($id, $role);
            self::syncMediaMember($id, $role, $data);
            return $id;
        });
    }

    public static function update(int $id, array $data, string $role): void
    {
        Database::transaction(static function () use ($id, $data, $role): void {
            Database::run(
                'UPDATE users SET name = :name, email = :email, whatsapp = :whatsapp WHERE id = :id',
                [
                    'name'     => $data['name'],
                    'email'    => mb_strtolower($data['email']),
                    'whatsapp' => $data['whatsapp'] ?: null,
                    'id'       => $id,
                ]
            );
            self::setRole($id, $role);
            self::syncMediaMember($id, $role, $data);
        });
    }

    /** Alteração dos próprios dados pelo titular (sem perfil). */
    public static function updateSelf(int $id, string $name, ?string $whatsapp): void
    {
        Database::run(
            'UPDATE users SET name = :name, whatsapp = :whatsapp WHERE id = :id',
            ['name' => $name, 'whatsapp' => $whatsapp, 'id' => $id]
        );
    }

    public static function setRole(int $userId, string $role): void
    {
        Database::run(
            'INSERT INTO user_roles (user_id, app_code, role) VALUES (:user_id, :app, :role)
             ON DUPLICATE KEY UPDATE role = VALUES(role)',
            ['user_id' => $userId, 'app' => APP_CODE, 'role' => $role]
        );
    }

    /** Mantém media_members coerente com o perfil: cria para equipe, remove para os demais. */
    private static function syncMediaMember(int $userId, string $role, array $data): void
    {
        if (in_array($role, Auth::MEDIA_ROLES, true)) {
            Database::run(
                'INSERT INTO media_members (user_id, member_status, joined_at, notes)
                 VALUES (:user_id, :status, :joined, :notes)
                 ON DUPLICATE KEY UPDATE member_status = VALUES(member_status), joined_at = VALUES(joined_at), notes = VALUES(notes)',
                [
                    'user_id' => $userId,
                    'status'  => $data['member_status'] ?? 'ativo',
                    'joined'  => ($data['joined_at'] ?? '') ?: null,
                    'notes'   => ($data['notes'] ?? '') ?: null,
                ]
            );
        } else {
            Database::run('DELETE FROM media_members WHERE user_id = :id', ['id' => $userId]);
            Database::run('DELETE FROM member_functions WHERE user_id = :id', ['id' => $userId]);
        }
    }

    public static function setPassword(int $id, string $hash, bool $mustChange): void
    {
        // session_version + 1 derruba as sessões abertas em outros aparelhos
        Database::run(
            'UPDATE users SET password_hash = :hash, must_change_password = :must, session_version = session_version + 1 WHERE id = :id',
            ['hash' => $hash, 'must' => $mustChange ? 1 : 0, 'id' => $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run(
            'UPDATE users SET status = :status, session_version = session_version + 1 WHERE id = :id',
            ['status' => $status, 'id' => $id]
        );
    }

    public static function bumpSession(int $id): void
    {
        Database::run('UPDATE users SET session_version = session_version + 1 WHERE id = :id', ['id' => $id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function setPhoto(int $id, ?string $path): void
    {
        Database::run('UPDATE users SET photo_path = :p WHERE id = :id', ['p' => $path, 'id' => $id]);
    }

    public static function countAdmins(?int $exceptId = null): int
    {
        $sql = "SELECT COUNT(*) FROM users u JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app
                 WHERE r.role = 'admin' AND u.status = 'ativo'";
        $params = ['app' => APP_CODE];
        if ($exceptId !== null) {
            $sql .= ' AND u.id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params);
    }

    /**
     * Anonimização LGPD: remove dados pessoais mantendo o id para integridade
     * de auditoria e vínculos históricos.
     */
    public static function anonymize(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            $user = self::find($id);
            if ($user && $user['photo_path']) {
                @unlink(STORAGE_PATH . '/photos/' . $user['photo_path']);
            }
            Database::run(
                "UPDATE users SET name = :name, email = :email, whatsapp = NULL, photo_path = NULL,
                        password_hash = :hash, status = 'anonimizado', anonymized_at = NOW(),
                        session_version = session_version + 1
                  WHERE id = :id",
                [
                    'name'  => 'Usuário removido #' . $id,
                    'email' => 'removido-' . $id . '@anonimizado.local',
                    'hash'  => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    'id'    => $id,
                ]
            );
            Database::run('DELETE FROM user_roles WHERE user_id = :id', ['id' => $id]);
            Database::run('DELETE FROM media_members WHERE user_id = :id', ['id' => $id]);
            Database::run('DELETE FROM member_functions WHERE user_id = :id', ['id' => $id]);
            Database::run('DELETE FROM ministry_users WHERE user_id = :id', ['id' => $id]);
            Database::run('UPDATE consents SET user_agent = NULL WHERE user_id = :id', ['id' => $id]);
        });
    }

    /** Dados para auditoria/exportação (sem senha). */
    public static function snapshot(?array $user): ?array
    {
        if (!$user) {
            return null;
        }
        unset($user['password_hash'], $user['session_version']);
        return $user;
    }

    /** Ministérios do usuário. */
    public static function ministries(int $id): array
    {
        return Database::all(
            'SELECT m.id, m.name, mu.is_leader FROM ministry_users mu JOIN ministries m ON m.id = mu.ministry_id
              WHERE mu.user_id = :id ORDER BY m.name',
            ['id' => $id]
        );
    }

    public static function syncMinistries(int $userId, array $ministryIds, array $leaderIds): void
    {
        Database::run('DELETE FROM ministry_users WHERE user_id = :id', ['id' => $userId]);
        foreach (array_unique(array_map('intval', $ministryIds)) as $mid) {
            if ($mid <= 0) {
                continue;
            }
            Database::run(
                'INSERT INTO ministry_users (ministry_id, user_id, is_leader) VALUES (:m, :u, :l)',
                ['m' => $mid, 'u' => $userId, 'l' => in_array($mid, array_map('intval', $leaderIds), true) ? 1 : 0]
            );
        }
    }
}
