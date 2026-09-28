<?php
// app/models/MediaFile.php — arquivos do repositório
declare(strict_types=1);

final class MediaFile
{
    public const STATUSES = [
        'quarentena' => 'Em quarentena',
        'aprovado'   => 'Aprovado',
        'rejeitado'  => 'Rejeitado',
        'lixeira'    => 'Na lixeira',
    ];

    private const SELECT = 'SELECT f.*, u.name AS uploader_name, g.guest_name, g.whatsapp AS guest_whatsapp, g.ministry_name AS guest_ministry,
                                   g.event_name AS guest_event, g.description AS guest_description
                              FROM files f
                         LEFT JOIN users u ON u.id = f.uploaded_by
                         LEFT JOIN guest_uploads g ON g.id = f.guest_upload_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE f.id = :id', ['id' => $id]);
    }

    public static function findBySha(string $sha): ?array
    {
        return Database::one(self::SELECT . " WHERE f.sha256 = :s AND f.status <> 'lixeira' ORDER BY f.id LIMIT 1", ['s' => $sha]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO files (folder_id, driver, storage_ref, display_ref, thumb_ref, original_name, extension, mime, size_bytes, sha256,
                                width, height, duration_seconds, category, title, description, visibility, status, event_name,
                                uploaded_by, guest_upload_id, upload_ip)
             VALUES (:folder_id, :driver, :storage_ref, :display_ref, :thumb_ref, :original_name, :extension, :mime, :size_bytes, :sha256,
                     :width, :height, :duration_seconds, :category, :title, :description, :visibility, :status, :event_name,
                     :uploaded_by, :guest_upload_id, :upload_ip)',
            $d
        );
    }

    /** Listagem de uma pasta (só o que o usuário pode ver). */
    public static function inFolder(?int $folderId, string $order = 'recentes'): array
    {
        [$where, $params] = Access::fileWhere();
        $folderSql = $folderId === null ? 'f.folder_id IS NULL' : 'f.folder_id = :folder';
        if ($folderId !== null) {
            $params['folder'] = $folderId;
        }
        return self::attachTags(Database::all(
            self::SELECT . " WHERE {$folderSql} AND f.status IN ('aprovado','quarentena') AND {$where} ORDER BY " . self::orderSql($order),
            $params
        ));
    }

    /** Busca paginada. */
    public static function search(array $filters, int $page, int $perPage = 40): array
    {
        [$where, $params] = Access::fileWhere();
        $conds = [$where, "f.status = 'aprovado'"];

        if (($filters['q'] ?? '') !== '') {
            // PDO nativo não aceita o mesmo placeholder repetido: um por coluna
            $conds[] = '(f.original_name LIKE :q1 OR f.title LIKE :q2 OR f.description LIKE :q3 OR f.event_name LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
                $params[$k] = '%' . $filters['q'] . '%';
            }
        }
        if (($filters['tag'] ?? '') !== '') {
            $conds[] = 'EXISTS (SELECT 1 FROM file_tags ft JOIN tags t ON t.id = ft.tag_id WHERE ft.file_id = f.id AND t.slug = :tag)';
            $params['tag'] = Tag::slugify($filters['tag']);
        }
        if (($filters['category'] ?? '') !== '') {
            $conds[] = 'f.category = :category';
            $params['category'] = $filters['category'];
        }
        if (($filters['uploader'] ?? '') !== '') {
            $conds[] = 'f.uploaded_by = :uploader';
            $params['uploader'] = (int) $filters['uploader'];
        }
        if (($filters['from'] ?? '') !== '') {
            $conds[] = 'f.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $conds[] = 'f.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        if (($filters['event'] ?? '') !== '') {
            $conds[] = 'f.event_name LIKE :event';
            $params['event'] = '%' . $filters['event'] . '%';
        }
        if (($filters['ministry_id'] ?? '') !== '') {
            $ids = [];
            foreach (Folder::all() as $id => $f) {
                if (Folder::effective($id)['ministry_id'] === (int) $filters['ministry_id']) {
                    $ids[] = $id;
                }
            }
            $conds[] = 'f.folder_id IN (' . ($ids ? implode(',', $ids) : '0') . ')';
        }
        if (($filters['folder_id'] ?? '') !== '') {
            $conds[] = 'f.folder_id IN (' . implode(',', Folder::subtreeIds((int) $filters['folder_id'])) . ')';
        }
        $whereSql = implode(' AND ', $conds);

        $total = (int) Database::value("SELECT COUNT(*) FROM files f WHERE {$whereSql}", $params);
        $pg = paginate($total, $page, $perPage);
        $rows = Database::all(
            self::SELECT . " WHERE {$whereSql} ORDER BY " . self::orderSql($filters['order'] ?? 'recentes') . ' LIMIT :limit OFFSET :offset',
            $params + ['limit' => $perPage, 'offset' => $pg['offset']]
        );
        return ['itens' => self::attachTags($rows)] + $pg;
    }

    private static function orderSql(string $order): string
    {
        return match ($order) {
            'nome'    => 'f.original_name ASC',
            'tamanho' => 'f.size_bytes DESC',
            'antigos' => 'f.created_at ASC',
            default   => 'f.created_at DESC, f.id DESC',
        };
    }

    /** Fila de moderação. */
    public static function quarantine(): array
    {
        return self::attachTags(Database::all(self::SELECT . " WHERE f.status = 'quarentena' ORDER BY f.created_at"));
    }

    public static function countQuarantine(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM files WHERE status = 'quarentena'");
    }

    public static function trash(): array
    {
        return Database::all(self::SELECT . " WHERE f.status = 'lixeira' ORDER BY f.trashed_at DESC");
    }

    public static function rejected(): array
    {
        return Database::all(self::SELECT . " WHERE f.status = 'rejeitado' ORDER BY f.moderated_at DESC");
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE files SET folder_id = :folder_id, title = :title, description = :description, category = :category,
                    visibility = :visibility, event_name = :event_name, has_restriction = :has_restriction WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function setStatus(int $id, string $status, array $extra = []): void
    {
        $sets = ['status = :status'];
        $params = ['status' => $status, 'id' => $id];
        foreach ($extra as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[$k] = $v;
        }
        Database::run('UPDATE files SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public static function attachTags(array $rows): array
    {
        if (!$rows) {
            return $rows;
        }
        $ids = array_column($rows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $tags = Database::all("SELECT ft.file_id, t.name, t.slug FROM file_tags ft JOIN tags t ON t.id = ft.tag_id WHERE ft.file_id IN ({$in}) ORDER BY t.name", $ids);
        $by = [];
        foreach ($tags as $t) {
            $by[$t['file_id']][] = $t;
        }
        foreach ($rows as &$r) {
            $r['tags'] = $by[$r['id']] ?? [];
        }
        return $rows;
    }

    public static function restrictions(int $fileId): array
    {
        return Database::all(
            'SELECT r.id, r.person_name, r.is_minor FROM file_restrictions fr JOIN image_restrictions r ON r.id = fr.restriction_id WHERE fr.file_id = :id ORDER BY r.person_name',
            ['id' => $fileId]
        );
    }

    public static function syncRestrictions(int $fileId, array $restrictionIds): void
    {
        Database::run('DELETE FROM file_restrictions WHERE file_id = :id', ['id' => $fileId]);
        foreach (array_unique(array_map('intval', $restrictionIds)) as $rid) {
            if ($rid > 0) {
                Database::run('INSERT IGNORE INTO file_restrictions (file_id, restriction_id) VALUES (:f, :r)', ['f' => $fileId, 'r' => $rid]);
            }
        }
    }

    /** Apaga do disco e do banco (uso do cron e do esvaziar lixeira). */
    public static function purge(array $file): void
    {
        $driver = Storage::driver();
        foreach (['storage_ref', 'display_ref', 'thumb_ref'] as $k) {
            if (!empty($file[$k])) {
                $driver->delete($file[$k]);
            }
        }
        Database::run('DELETE FROM files WHERE id = :id', ['id' => $file['id']]);
    }

    /** Arquivos aprovados de uma subárvore de pastas (para ZIP). */
    public static function approvedInSubtree(int $folderId): array
    {
        [$where, $params] = Access::fileWhere();
        $ids = implode(',', Folder::subtreeIds($folderId));
        return Database::all(self::SELECT . " WHERE f.folder_id IN ({$ids}) AND f.status = 'aprovado' AND {$where} ORDER BY f.folder_id, f.original_name", $params);
    }

    public static function totals(): array
    {
        return Database::one("SELECT COUNT(*) AS qty, COALESCE(SUM(size_bytes),0) AS bytes FROM files WHERE status <> 'lixeira'") ?? ['qty' => 0, 'bytes' => 0];
    }

    public static function usageByCategory(): array
    {
        return Database::all("SELECT category, COUNT(*) AS qty, COALESCE(SUM(size_bytes),0) AS bytes FROM files WHERE status <> 'lixeira' GROUP BY category ORDER BY bytes DESC");
    }

    public static function usageByUser(int $limit = 15): array
    {
        return Database::all(
            "SELECT u.id, u.name, r.role, COUNT(*) AS qty, COALESCE(SUM(f.size_bytes),0) AS bytes
               FROM files f JOIN users u ON u.id = f.uploaded_by
          LEFT JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app
              WHERE f.status <> 'lixeira' GROUP BY u.id, u.name, r.role ORDER BY bytes DESC LIMIT :l",
            ['app' => APP_CODE, 'l' => $limit]
        );
    }

    public static function recentForUser(int $userId, int $limit = 8): array
    {
        return Database::all(self::SELECT . ' WHERE f.uploaded_by = :u ORDER BY f.id DESC LIMIT :l', ['u' => $userId, 'l' => $limit]);
    }
}
