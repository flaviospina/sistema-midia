<?php
// app/models/Folder.php — pastas hierárquicas do repositório
declare(strict_types=1);

final class Folder
{
    public const VISIBILITIES = [
        'restrito'   => 'Restrito (só administradores)',
        'midia'      => 'Equipe de mídia',
        'ministerio' => 'Equipe + ministério dono',
        'membros'    => 'Todos os usuários logados',
    ];

    private static ?array $cache = null;   // id => linha
    private static array $effective = [];  // id => ['visibility'=>, 'ministry_id'=>]

    /** Todas as pastas (poucas centenas no máximo), cacheadas por requisição. */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT * FROM folders ORDER BY sort_order, name') as $row) {
                self::$cache[(int) $row['id']] = $row;
            }
        }
        return self::$cache;
    }

    public static function find(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function children(?int $parentId): array
    {
        return array_values(array_filter(self::all(), static fn($f) => (int) ($f['parent_id'] ?? 0) === (int) $parentId));
    }

    /** Caminho da raiz até a pasta (inclusive). */
    public static function breadcrumb(?int $id): array
    {
        $chain = [];
        $guard = 0;
        while ($id && ($f = self::find($id)) && $guard++ < 50) {
            array_unshift($chain, $f);
            $id = $f['parent_id'] ? (int) $f['parent_id'] : null;
        }
        return $chain;
    }

    /** Visibilidade e ministério efetivos (herdados). Raiz sem definição = 'midia'. */
    public static function effective(?int $id): array
    {
        if (!$id) {
            return ['visibility' => 'midia', 'ministry_id' => null];
        }
        if (isset(self::$effective[$id])) {
            return self::$effective[$id];
        }
        $vis = null;
        $min = null;
        $guard = 0;
        $cur = $id;
        while ($cur && ($f = self::find($cur)) && $guard++ < 50) {
            $vis ??= $f['visibility'] ?: null;
            $min ??= $f['ministry_id'] ? (int) $f['ministry_id'] : null;
            if ($vis !== null && $min !== null) {
                break;
            }
            $cur = $f['parent_id'] ? (int) $f['parent_id'] : null;
        }
        return self::$effective[$id] = ['visibility' => $vis ?? 'midia', 'ministry_id' => $min];
    }

    public static function pathLabel(?int $id): string
    {
        $chain = self::breadcrumb($id);
        return $chain ? implode(' › ', array_column($chain, 'name')) : 'Raiz';
    }

    public static function isDescendant(int $folderId, int $ancestorId): bool
    {
        foreach (self::breadcrumb($folderId) as $f) {
            if ((int) $f['id'] === $ancestorId) {
                return true;
            }
        }
        return false;
    }

    /** IDs da pasta e de toda a subárvore. */
    public static function subtreeIds(int $id): array
    {
        $ids = [$id];
        foreach (self::children($id) as $c) {
            $ids = array_merge($ids, self::subtreeIds((int) $c['id']));
        }
        return $ids;
    }

    public static function create(array $data): int
    {
        $id = Database::insert(
            'INSERT INTO folders (parent_id, name, description, visibility, ministry_id, sort_order, created_by)
             VALUES (:parent, :name, :description, :visibility, :ministry, :sort, :by)',
            [
                'parent' => $data['parent_id'] ?: null, 'name' => $data['name'], 'description' => $data['description'] ?: null,
                'visibility' => $data['visibility'] ?: null, 'ministry' => $data['ministry_id'] ?: null,
                'sort' => (int) ($data['sort_order'] ?? 0), 'by' => Auth::id(),
            ]
        );
        self::reset();
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE folders SET parent_id = :parent, name = :name, description = :description, visibility = :visibility,
                    ministry_id = :ministry, sort_order = :sort WHERE id = :id',
            [
                'parent' => $data['parent_id'] ?: null, 'name' => $data['name'], 'description' => $data['description'] ?: null,
                'visibility' => $data['visibility'] ?: null, 'ministry' => $data['ministry_id'] ?: null,
                'sort' => (int) ($data['sort_order'] ?? 0), 'id' => $id,
            ]
        );
        self::reset();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM folders WHERE id = :id', ['id' => $id]);
        self::reset();
    }

    public static function fileCount(int $id): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM files WHERE folder_id = :id AND status <> 'lixeira'", ['id' => $id]);
    }

    /** Uso em bytes por pasta de primeiro nível (inclui subpastas). */
    public static function usageByRoot(): array
    {
        $rows = Database::all("SELECT folder_id, SUM(size_bytes) AS bytes, COUNT(*) AS qty FROM files WHERE status <> 'lixeira' GROUP BY folder_id");
        $out = [];
        foreach ($rows as $r) {
            $chain = self::breadcrumb($r['folder_id'] ? (int) $r['folder_id'] : null);
            $root = $chain ? $chain[0]['name'] : ($r['folder_id'] === null ? 'Sem pasta (quarentena)' : 'Outros');
            $out[$root] ??= ['bytes' => 0, 'qty' => 0];
            $out[$root]['bytes'] += (int) $r['bytes'];
            $out[$root]['qty'] += (int) $r['qty'];
        }
        arsort($out);
        return $out;
    }

    public static function reset(): void
    {
        self::$cache = null;
        self::$effective = [];
    }

    /** Opções "id => caminho" para selects, opcionalmente só as que o usuário pode usar para envio. */
    public static function options(?callable $filter = null): array
    {
        $out = [];
        foreach (self::all() as $id => $f) {
            if ($filter === null || $filter($f)) {
                $out[$id] = self::pathLabel($id);
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}
