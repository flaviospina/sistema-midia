<?php
// app/models/Equipment.php — patrimônio: itens, empréstimos e manutenção
declare(strict_types=1);

final class Equipment
{
    public const CATEGORIES = [
        'som' => 'Som', 'video' => 'Vídeo / câmeras', 'iluminacao' => 'Iluminação', 'informatica' => 'Informática',
        'cabo' => 'Cabos', 'acessorio' => 'Acessórios', 'outro' => 'Outro',
    ];

    public const STATUSES = ['disponivel' => 'Disponível', 'emprestado' => 'Emprestado', 'manutencao' => 'Em manutenção', 'baixado' => 'Baixado'];

    public const STATUS_COLORS = ['disponivel' => 'success', 'emprestado' => 'warning', 'manutencao' => 'danger', 'baixado' => 'secondary'];

    private const SELECT = 'SELECT e.*,
                                   (SELECT l.user_id FROM equipment_loans l WHERE l.equipment_id = e.id AND l.returned_at IS NULL ORDER BY l.id DESC LIMIT 1) AS loan_user_id,
                                   (SELECT u.name FROM equipment_loans l JOIN users u ON u.id = l.user_id WHERE l.equipment_id = e.id AND l.returned_at IS NULL ORDER BY l.id DESC LIMIT 1) AS loan_user_name,
                                   (SELECT l.due_on FROM equipment_loans l WHERE l.equipment_id = e.id AND l.returned_at IS NULL ORDER BY l.id DESC LIMIT 1) AS loan_due_on
                              FROM equipment e';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE e.id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{32}$/', $token) ? Database::one(self::SELECT . ' WHERE e.qr_token = :t', ['t' => $token]) : null;
    }

    public static function search(array $f, int $page, int $perPage = 40): array
    {
        $conds = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $conds[] = '(e.code LIKE :q1 OR e.name LIKE :q2 OR e.brand LIKE :q3 OR e.model LIKE :q4 OR e.serial_number LIKE :q5 OR e.location LIKE :q6)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $k) {
                $params[$k] = '%' . $f['q'] . '%';
            }
        }
        if (($f['category'] ?? '') !== '') {
            $conds[] = 'e.category = :cat';
            $params['cat'] = $f['category'];
        }
        if (($f['status'] ?? '') !== '') {
            $conds[] = 'e.status = :st';
            $params['st'] = $f['status'];
        } elseif (empty($f['all'])) {
            $conds[] = "e.status <> 'baixado'";
        }
        $where = implode(' AND ', $conds);
        $total = (int) Database::value("SELECT COUNT(*) FROM equipment e WHERE {$where}", $params);
        $pg = paginate($total, $page, $perPage);
        return ['itens' => Database::all(self::SELECT . " WHERE {$where} ORDER BY e.code LIMIT :limit OFFSET :offset", $params + ['limit' => $perPage, 'offset' => $pg['offset']])] + $pg;
    }

    public static function nextCode(): string
    {
        $max = (int) Database::value("SELECT MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) FROM equipment WHERE code LIKE 'MID-%'");
        return sprintf('MID-%04d', $max + 1);
    }

    public static function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM equipment WHERE code = :c';
        $params = ['c' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO equipment (code, name, category, brand, model, serial_number, acquired_on, value_cents, location, status, notes, qr_token, created_by)
             VALUES (:code, :name, :category, :brand, :model, :serial_number, :acquired_on, :value_cents, :location, :status, :notes, :qr, :by)',
            $d + ['qr' => bin2hex(random_bytes(16)), 'by' => Auth::id()]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE equipment SET code = :code, name = :name, category = :category, brand = :brand, model = :model, serial_number = :serial_number,
                    acquired_on = :acquired_on, value_cents = :value_cents, location = :location, status = :status, notes = :notes WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE equipment SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    public static function setPhoto(int $id, ?string $path): void
    {
        Database::run('UPDATE equipment SET photo_path = :p WHERE id = :id', ['p' => $path, 'id' => $id]);
    }

    // ---- Empréstimos ---------------------------------------------------

    public static function loans(int $equipmentId, int $limit = 30): array
    {
        return Database::all(
            'SELECT l.*, u.name AS user_name, b.name AS loaned_by_name, r.name AS returned_by_name FROM equipment_loans l
               JOIN users u ON u.id = l.user_id LEFT JOIN users b ON b.id = l.loaned_by LEFT JOIN users r ON r.id = l.returned_by
              WHERE l.equipment_id = :e ORDER BY l.id DESC LIMIT :l',
            ['e' => $equipmentId, 'l' => $limit]
        );
    }

    public static function openLoan(int $equipmentId): ?array
    {
        return Database::one('SELECT l.*, u.name AS user_name FROM equipment_loans l JOIN users u ON u.id = l.user_id WHERE l.equipment_id = :e AND l.returned_at IS NULL ORDER BY l.id DESC LIMIT 1', ['e' => $equipmentId]);
    }

    public static function loan(int $equipmentId, int $userId, ?string $purpose, ?string $dueOn): int
    {
        return Database::transaction(static function () use ($equipmentId, $userId, $purpose, $dueOn): int {
            $id = Database::insert(
                'INSERT INTO equipment_loans (equipment_id, user_id, purpose, due_on, loaned_by) VALUES (:e, :u, :p, :d, :by)',
                ['e' => $equipmentId, 'u' => $userId, 'p' => $purpose, 'd' => $dueOn, 'by' => Auth::id()]
            );
            self::setStatus($equipmentId, 'emprestado');
            return $id;
        });
    }

    public static function returnLoan(int $loanId, string $condition, ?string $notes): void
    {
        $loan = Database::one('SELECT * FROM equipment_loans WHERE id = :id', ['id' => $loanId]);
        if (!$loan || $loan['returned_at'] !== null) {
            return;
        }
        Database::transaction(static function () use ($loan, $condition, $notes): void {
            Database::run(
                'UPDATE equipment_loans SET returned_at = NOW(), returned_condition = :c, notes = :n, returned_by = :by WHERE id = :id',
                ['c' => $condition, 'n' => $notes, 'by' => Auth::id(), 'id' => $loan['id']]
            );
            self::setStatus((int) $loan['equipment_id'], $condition === 'danificado' ? 'manutencao' : 'disponivel');
        });
    }

    public static function overdueLoans(): array
    {
        return Database::all(
            'SELECT l.*, u.name AS user_name, u.whatsapp, e.code, e.name AS equipment_name FROM equipment_loans l JOIN users u ON u.id = l.user_id JOIN equipment e ON e.id = l.equipment_id
              WHERE l.returned_at IS NULL AND l.due_on IS NOT NULL AND l.due_on < CURDATE() ORDER BY l.due_on'
        );
    }

    public static function myLoans(int $userId): array
    {
        return Database::all(
            'SELECT l.*, e.code, e.name AS equipment_name FROM equipment_loans l JOIN equipment e ON e.id = l.equipment_id WHERE l.user_id = :u AND l.returned_at IS NULL ORDER BY l.due_on',
            ['u' => $userId]
        );
    }

    // ---- Manutenção ----------------------------------------------------

    public static function maintenance(int $equipmentId): array
    {
        return Database::all('SELECT m.*, u.name AS opened_by_name FROM equipment_maintenance m LEFT JOIN users u ON u.id = m.opened_by WHERE m.equipment_id = :e ORDER BY m.id DESC', ['e' => $equipmentId]);
    }

    public static function openMaintenance(int $equipmentId, array $d): int
    {
        return Database::transaction(static function () use ($equipmentId, $d): int {
            $id = Database::insert(
                'INSERT INTO equipment_maintenance (equipment_id, kind, description, provider, opened_on, cost_cents, opened_by) VALUES (:e, :kind, :description, :provider, :opened_on, :cost_cents, :by)',
                $d + ['e' => $equipmentId, 'by' => Auth::id()]
            );
            self::setStatus($equipmentId, 'manutencao');
            return $id;
        });
    }

    public static function closeMaintenance(int $maintenanceId, ?string $result, ?int $costCents, bool $backToService): void
    {
        $m = Database::one('SELECT * FROM equipment_maintenance WHERE id = :id', ['id' => $maintenanceId]);
        if (!$m || $m['closed_on'] !== null) {
            return;
        }
        Database::transaction(static function () use ($m, $result, $costCents, $backToService): void {
            Database::run('UPDATE equipment_maintenance SET closed_on = CURDATE(), result = :r, cost_cents = COALESCE(:c, cost_cents) WHERE id = :id', ['r' => $result, 'c' => $costCents, 'id' => $m['id']]);
            self::setStatus((int) $m['equipment_id'], $backToService ? 'disponivel' : 'baixado');
        });
    }

    public static function summary(): array
    {
        $out = ['total' => 0, 'valor' => 0, 'por_status' => [], 'por_categoria' => []];
        foreach (Database::all("SELECT status, COUNT(*) n, COALESCE(SUM(value_cents),0) v FROM equipment GROUP BY status") as $r) {
            $out['por_status'][$r['status']] = (int) $r['n'];
            if ($r['status'] !== 'baixado') {
                $out['total'] += (int) $r['n'];
                $out['valor'] += (int) $r['v'];
            }
        }
        foreach (Database::all("SELECT category, COUNT(*) n FROM equipment WHERE status <> 'baixado' GROUP BY category") as $r) {
            $out['por_categoria'][$r['category']] = (int) $r['n'];
        }
        return $out;
    }
}
