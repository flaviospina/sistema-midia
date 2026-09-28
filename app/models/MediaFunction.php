<?php
// app/models/MediaFunction.php — funções da equipe (som, projeção, ...)
declare(strict_types=1);

final class MediaFunction
{
    public const LEVELS = [
        'aprendiz'   => 'Aprendiz',
        'apto'       => 'Apto',
        'referencia' => 'Referência',
    ];

    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT f.*, (SELECT COUNT(*) FROM member_functions mf WHERE mf.function_id = f.id) AS members_count
                  FROM media_functions f';
        if ($onlyActive) {
            $sql .= ' WHERE f.active = 1';
        }
        return Database::all($sql . ' ORDER BY f.sort_order, f.name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM media_functions WHERE id = :id', ['id' => $id]);
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM media_functions WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public static function slugify(string $name): string
    {
        $slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $slug), '-'));
        return $slug !== '' ? substr($slug, 0, 80) : 'funcao-' . time();
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO media_functions (name, slug, description, sort_order, active)
             VALUES (:name, :slug, :description, :sort_order, :active)',
            [
                'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?: null,
                'sort_order' => (int) $data['sort_order'], 'active' => (int) $data['active'],
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE media_functions SET name = :name, slug = :slug, description = :description,
                    sort_order = :sort_order, active = :active WHERE id = :id',
            [
                'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?: null,
                'sort_order' => (int) $data['sort_order'], 'active' => (int) $data['active'], 'id' => $id,
            ]
        );
    }

    /** Funções de um membro, indexadas por function_id. */
    public static function forMember(int $userId): array
    {
        $rows = Database::all(
            'SELECT mf.*, f.name, f.slug FROM member_functions mf JOIN media_functions f ON f.id = mf.function_id
              WHERE mf.user_id = :u ORDER BY f.sort_order',
            ['u' => $userId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['function_id']] = $r;
        }
        return $out;
    }

    /**
     * Substitui as funções de um membro.
     * $items: [function_id => ['level' => ..., 'trained_at' => ..., 'is_coordinator' => bool]]
     */
    public static function syncMember(int $userId, array $items): void
    {
        Database::transaction(static function () use ($userId, $items): void {
            Database::run('DELETE FROM member_functions WHERE user_id = :u', ['u' => $userId]);
            foreach ($items as $functionId => $it) {
                Database::run(
                    'INSERT INTO member_functions (user_id, function_id, level, trained_at, is_coordinator)
                     VALUES (:u, :f, :level, :trained, :coord)',
                    [
                        'u' => $userId, 'f' => (int) $functionId, 'level' => $it['level'],
                        'trained' => $it['trained_at'] ?: null, 'coord' => $it['is_coordinator'] ? 1 : 0,
                    ]
                );
            }
        });
    }
}
