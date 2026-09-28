<?php
// app/models/Ministry.php
declare(strict_types=1);

final class Ministry
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT m.*,
                       (SELECT COUNT(*) FROM ministry_users mu WHERE mu.ministry_id = m.id) AS members_count,
                       (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ", ")
                          FROM ministry_users mu JOIN users u ON u.id = mu.user_id
                         WHERE mu.ministry_id = m.id AND mu.is_leader = 1) AS leaders
                  FROM ministries m';
        if ($onlyActive) {
            $sql .= ' WHERE m.active = 1';
        }
        return Database::all($sql . ' ORDER BY m.name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM ministries WHERE id = :id', ['id' => $id]);
    }

    public static function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ministries WHERE name = :name';
        $params = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO ministries (name, description, active) VALUES (:name, :description, :active)',
            ['name' => $data['name'], 'description' => $data['description'] ?: null, 'active' => (int) $data['active']]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE ministries SET name = :name, description = :description, active = :active WHERE id = :id',
            ['name' => $data['name'], 'description' => $data['description'] ?: null, 'active' => (int) $data['active'], 'id' => $id]
        );
    }

    public static function members(int $id): array
    {
        return Database::all(
            'SELECT u.id, u.name, u.email, mu.is_leader FROM ministry_users mu JOIN users u ON u.id = mu.user_id
              WHERE mu.ministry_id = :id AND u.status <> "anonimizado" ORDER BY mu.is_leader DESC, u.name',
            ['id' => $id]
        );
    }
}
