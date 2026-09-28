<?php
// app/models/AuditLog.php — consulta da trilha de auditoria
declare(strict_types=1);

final class AuditLog
{
    public static function search(array $filters, int $page, int $perPage = 50): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filters['user_id'] ?? '') !== '') {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (($filters['entity'] ?? '') !== '') {
            $where[] = 'a.entity = :entity';
            $params['entity'] = $filters['entity'];
        }
        if (($filters['action'] ?? '') !== '') {
            $where[] = 'a.action LIKE :action';
            $params['action'] = '%' . $filters['action'] . '%';
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'a.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'a.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::value("SELECT COUNT(*) FROM audit_log a WHERE {$whereSql}", $params);
        $pg = paginate($total, $page, $perPage);
        $rows = Database::all(
            "SELECT a.id, a.user_id, a.action, a.entity, a.entity_id, a.ip, a.created_at, u.name AS user_name
               FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
              WHERE {$whereSql}
           ORDER BY a.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => $pg['offset']]
        );
        return ['itens' => $rows] + $pg;
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function entities(): array
    {
        return array_column(Database::all('SELECT DISTINCT entity FROM audit_log ORDER BY entity'), 'entity');
    }

    public static function recent(int $limit = 10): array
    {
        return Database::all(
            'SELECT a.id, a.action, a.entity, a.entity_id, a.created_at, u.name AS user_name
               FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
           ORDER BY a.id DESC LIMIT :l',
            ['l' => $limit]
        );
    }
}
