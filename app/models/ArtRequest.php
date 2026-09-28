<?php
// app/models/ArtRequest.php — pedidos de arte (briefing, versões, comentários, aprovações, checklist)
declare(strict_types=1);

final class ArtRequest
{
    public const FORMATS = [
        'story'    => 'Story (1080×1920)',
        'feed'     => 'Feed (1080×1350)',
        'telao'    => 'Telão (1920×1080)',
        'impresso' => 'Impresso (boletim, faixa, cartaz)',
    ];

    public const STATUSES = [
        'recebido'             => 'Recebido',
        'em_producao'          => 'Em produção',
        'revisao_solicitante'  => 'Revisão do solicitante',
        'aprovacao_midia'      => 'Aprovação da mídia',
        'aprovacao_pastoral'   => 'Aprovação pastoral',
        'aprovado'             => 'Aprovado',
        'publicado'            => 'Publicado',
        'ajustes'              => 'Em ajustes',
        'cancelado'            => 'Cancelado',
    ];

    /** Colunas do kanban, na ordem. */
    public const KANBAN = ['recebido', 'em_producao', 'ajustes', 'revisao_solicitante', 'aprovacao_midia', 'aprovacao_pastoral', 'aprovado'];

    public const STATUS_COLORS = [
        'recebido' => 'secondary', 'em_producao' => 'primary', 'revisao_solicitante' => 'info', 'aprovacao_midia' => 'warning',
        'aprovacao_pastoral' => 'warning', 'aprovado' => 'success', 'publicado' => 'dark', 'ajustes' => 'danger', 'cancelado' => 'light',
    ];

    private const SELECT = 'SELECT r.*, u.name AS requester_name, u.whatsapp AS requester_whatsapp, d.name AS designer_name,
                                   m.name AS ministry_name, e.title AS event_title, e.starts_at AS event_starts_at,
                                   (SELECT COUNT(*) FROM art_request_versions v WHERE v.request_id = r.id) AS versions_count,
                                   (SELECT COUNT(*) FROM art_request_comments c WHERE c.request_id = r.id AND c.kind = "comentario") AS comments_count
                              FROM art_requests r
                              JOIN users u ON u.id = r.requester_id
                         LEFT JOIN users d ON d.id = r.designer_id
                         LEFT JOIN ministries m ON m.id = r.ministry_id
                         LEFT JOIN events e ON e.id = r.event_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE r.id = :id', ['id' => $id]);
    }

    /** Condição de visibilidade: quem vê tudo, ou o solicitante, ou líder do ministério do pedido. */
    private static function visibleWhere(): array
    {
        if (Auth::can('art.view_all')) {
            return ['1=1', []];
        }
        $ids = Auth::ministryIds();
        $in = $ids ? implode(',', $ids) : '0';
        return ["(r.requester_id = :vis_uid OR r.ministry_id IN ({$in}))", ['vis_uid' => Auth::id()]];
    }

    public static function canView(array $r): bool
    {
        if (Auth::can('art.view_all')) {
            return true;
        }
        return (int) $r['requester_id'] === Auth::id() || ($r['ministry_id'] !== null && in_array((int) $r['ministry_id'], Auth::ministryIds(), true));
    }

    public static function isRequester(array $r): bool
    {
        return (int) $r['requester_id'] === Auth::id() || ($r['ministry_id'] !== null && in_array((int) $r['ministry_id'], Auth::ministryIds(), true) && Auth::is('lider_ministerio'));
    }

    public static function search(array $f, int $page, int $perPage = 30): array
    {
        [$where, $params] = self::visibleWhere();
        $conds = [$where];
        if (($f['status'] ?? '') !== '') {
            $conds[] = 'r.status = :status';
            $params['status'] = $f['status'];
        } elseif (empty($f['all'])) {
            $conds[] = "r.status NOT IN ('publicado','cancelado')";
        }
        if (($f['ministry_id'] ?? '') !== '') {
            $conds[] = 'r.ministry_id = :ministry';
            $params['ministry'] = (int) $f['ministry_id'];
        }
        if (($f['designer_id'] ?? '') !== '') {
            $conds[] = 'r.designer_id = :designer';
            $params['designer'] = (int) $f['designer_id'];
        }
        if (($f['q'] ?? '') !== '') {
            $conds[] = '(r.title LIKE :q1 OR r.briefing LIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['mine'])) {
            $conds[] = '(r.requester_id = :me1 OR r.designer_id = :me2)';
            $params['me1'] = $params['me2'] = Auth::id();
        }
        $sql = implode(' AND ', $conds);
        $total = (int) Database::value("SELECT COUNT(*) FROM art_requests r WHERE {$sql}", $params);
        $pg = paginate($total, $page, $perPage);
        $rows = Database::all(self::SELECT . " WHERE {$sql} ORDER BY r.publish_on, r.id LIMIT :limit OFFSET :offset", $params + ['limit' => $perPage, 'offset' => $pg['offset']]);
        return ['itens' => $rows] + $pg;
    }

    /** Pedidos ativos agrupados por status (kanban). */
    public static function kanban(): array
    {
        $rows = Database::all(self::SELECT . " WHERE r.status NOT IN ('publicado','cancelado') ORDER BY r.publish_on, r.id");
        $out = array_fill_keys(self::KANBAN, []);
        foreach ($rows as $r) {
            $out[$r['status']][] = $r;
        }
        return $out;
    }

    /** Atrasados e em risco: publicação passada ou a menos de 3 dias sem aprovação. */
    public static function delays(): array
    {
        return Database::all(
            self::SELECT . " WHERE r.status NOT IN ('aprovado','publicado','cancelado') AND r.publish_on <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) ORDER BY r.publish_on"
        );
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO art_requests (title, requester_id, ministry_id, event_id, briefing, texts, formats, publish_on, is_urgent, needs_pastoral)
             VALUES (:title, :requester_id, :ministry_id, :event_id, :briefing, :texts, :formats, :publish_on, :is_urgent, :needs_pastoral)',
            $d
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE art_requests SET title = :title, ministry_id = :ministry_id, event_id = :event_id, briefing = :briefing, texts = :texts,
                    formats = :formats, publish_on = :publish_on, is_urgent = :is_urgent, needs_pastoral = :needs_pastoral WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function set(int $id, array $fields): void
    {
        $sets = [];
        foreach ($fields as $k => $v) {
            $sets[] = "{$k} = :{$k}";
        }
        Database::run('UPDATE art_requests SET ' . implode(', ', $sets) . ' WHERE id = :id', $fields + ['id' => $id]);
    }

    public static function formatsOf(array $r): array
    {
        return array_values(array_filter(explode(',', (string) $r['formats'])));
    }

    public static function needsPastoral(array $formats): bool
    {
        return array_intersect($formats, ART_PASTORAL_FORMATS) !== [];
    }

    public static function isUrgent(string $publishOn): bool
    {
        return (strtotime($publishOn) - strtotime(date('Y-m-d'))) / 86400 < ART_MIN_DAYS;
    }

    // ---- Versões e anexos ----------------------------------------------

    public static function versions(int $id): array
    {
        return Database::all(
            'SELECT v.*, f.original_name, f.thumb_ref, f.extension, f.category, f.size_bytes, u.name AS creator_name
               FROM art_request_versions v JOIN files f ON f.id = v.file_id LEFT JOIN users u ON u.id = v.created_by
              WHERE v.request_id = :r ORDER BY v.version_no DESC',
            ['r' => $id]
        );
    }

    public static function currentVersion(int $id): ?array
    {
        return Database::one('SELECT * FROM art_request_versions WHERE request_id = :r ORDER BY version_no DESC LIMIT 1', ['r' => $id]);
    }

    public static function addVersion(int $id, int $fileId, ?string $notes, ?int $userId): int
    {
        return Database::transaction(static function () use ($id, $fileId, $notes, $userId): int {
            $no = (int) Database::value('SELECT COALESCE(MAX(version_no),0) + 1 FROM art_request_versions WHERE request_id = :r', ['r' => $id]);
            $vid = Database::insert(
                'INSERT INTO art_request_versions (request_id, version_no, file_id, notes, created_by) VALUES (:r, :n, :f, :notes, :by)',
                ['r' => $id, 'n' => $no, 'f' => $fileId, 'notes' => $notes, 'by' => $userId]
            );
            Database::run('UPDATE art_requests SET current_version = :n WHERE id = :id', ['n' => $no, 'id' => $id]);
            Database::run('UPDATE files SET art_request_id = :r WHERE id = :f', ['r' => $id, 'f' => $fileId]);
            return $vid;
        });
    }

    public static function attachments(int $id): array
    {
        return Database::all(
            'SELECT f.id, f.original_name, f.thumb_ref, f.extension, f.category, f.size_bytes, f.created_at
               FROM art_request_attachments a JOIN files f ON f.id = a.file_id WHERE a.request_id = :r ORDER BY f.id',
            ['r' => $id]
        );
    }

    public static function addAttachment(int $id, int $fileId): void
    {
        Database::run('INSERT IGNORE INTO art_request_attachments (request_id, file_id) VALUES (:r, :f)', ['r' => $id, 'f' => $fileId]);
        Database::run('UPDATE files SET art_request_id = :r WHERE id = :f', ['r' => $id, 'f' => $fileId]);
    }

    // ---- Comentários / histórico ---------------------------------------

    public static function comments(int $id): array
    {
        return Database::all(
            'SELECT c.*, u.name AS user_name, v.version_no FROM art_request_comments c LEFT JOIN users u ON u.id = c.user_id
          LEFT JOIN art_request_versions v ON v.id = c.version_id WHERE c.request_id = :r ORDER BY c.id',
            ['r' => $id]
        );
    }

    public static function comment(int $id, string $body, string $kind = 'comentario', ?int $versionId = null, ?int $userId = null): int
    {
        return Database::insert(
            'INSERT INTO art_request_comments (request_id, version_id, user_id, kind, body) VALUES (:r, :v, :u, :k, :b)',
            ['r' => $id, 'v' => $versionId, 'u' => $userId ?? Auth::id(), 'k' => $kind, 'b' => $body]
        );
    }

    public static function approval(int $id, ?int $versionId, string $stage, string $decision, ?string $notes): void
    {
        Database::run(
            'INSERT INTO approvals (request_id, version_id, stage, decision, user_id, notes) VALUES (:r, :v, :s, :d, :u, :n)',
            ['r' => $id, 'v' => $versionId, 's' => $stage, 'd' => $decision, 'u' => Auth::id(), 'n' => $notes]
        );
    }

    public static function approvals(int $id): array
    {
        return Database::all('SELECT a.*, u.name AS user_name, v.version_no FROM approvals a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN art_request_versions v ON v.id = a.version_id WHERE a.request_id = :r ORDER BY a.id', ['r' => $id]);
    }

    // ---- Checklist -----------------------------------------------------

    public static function checklistItems(bool $onlyActive = true): array
    {
        return Database::all('SELECT * FROM art_checklist_items' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id');
    }

    public static function checks(int $id): array
    {
        return array_map('intval', array_column(Database::all('SELECT item_id FROM art_request_checks WHERE request_id = :r', ['r' => $id]), 'item_id'));
    }

    public static function saveChecks(int $id, array $itemIds): void
    {
        Database::transaction(static function () use ($id, $itemIds): void {
            Database::run('DELETE FROM art_request_checks WHERE request_id = :r', ['r' => $id]);
            foreach (array_unique(array_map('intval', $itemIds)) as $iid) {
                if ($iid > 0) {
                    Database::run('INSERT IGNORE INTO art_request_checks (request_id, item_id, checked_by) VALUES (:r, :i, :u)', ['r' => $id, 'i' => $iid, 'u' => Auth::id()]);
                }
            }
        });
    }

    public static function checklistComplete(int $id): bool
    {
        $active = array_map('intval', array_column(self::checklistItems(), 'id'));
        return array_diff($active, self::checks($id)) === [];
    }

    public static function saveChecklistItems(array $rows): void
    {
        Database::transaction(static function () use ($rows): void {
            $keep = [];
            foreach ($rows as $row) {
                $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 150);
                if ($label === '') {
                    continue;
                }
                $order = (int) ($row['sort_order'] ?? 0);
                $active = !empty($row['active']) ? 1 : 0;
                if (!empty($row['id']) && ctype_digit((string) $row['id'])) {
                    Database::run('UPDATE art_checklist_items SET label = :l, sort_order = :o, active = :a WHERE id = :id', ['l' => $label, 'o' => $order, 'a' => $active, 'id' => (int) $row['id']]);
                    $keep[] = (int) $row['id'];
                } else {
                    $keep[] = Database::insert('INSERT INTO art_checklist_items (label, sort_order, active) VALUES (:l, :o, :a)', ['l' => $label, 'o' => $order, 'a' => $active]);
                }
            }
            // Itens removidos do formulário ficam inativos (preserva histórico)
            if ($keep) {
                Database::run('UPDATE art_checklist_items SET active = 0 WHERE id NOT IN (' . implode(',', $keep) . ')');
            }
        });
    }

    /** Pasta do sistema para anexos/versões (criada se não existir). */
    public static function folderId(): int
    {
        foreach (Folder::all() as $id => $f) {
            if ($f['parent_id'] === null && $f['name'] === ART_FOLDER_NAME) {
                return $id;
            }
        }
        $id = Database::insert('INSERT INTO folders (name, visibility, is_system, sort_order) VALUES (:n, "midia", 1, 50)', ['n' => ART_FOLDER_NAME]);
        Folder::reset();
        return $id;
    }

    // ---- Painel ---------------------------------------------------------

    public static function countsForDashboard(): array
    {
        $uid = Auth::id();
        return [
            'minha_revisao'  => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE status = 'revisao_solicitante' AND requester_id = :u", ['u' => $uid]),
            'meus_pedidos'   => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE requester_id = :u AND status NOT IN ('publicado','cancelado')", ['u' => $uid]),
            'minha_producao' => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE designer_id = :u AND status IN ('em_producao','ajustes')", ['u' => $uid]),
            'sem_designer'   => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE status = 'recebido'"),
            'aprov_midia'    => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE status = 'aprovacao_midia'"),
            'aprov_pastoral' => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE status = 'aprovacao_pastoral'"),
            'atrasados'      => (int) Database::value("SELECT COUNT(*) FROM art_requests WHERE status NOT IN ('aprovado','publicado','cancelado') AND publish_on < CURDATE()"),
        ];
    }

    public static function topMinistries(int $days = 180): array
    {
        return Database::all(
            'SELECT m.name, COUNT(*) AS qty FROM art_requests r JOIN ministries m ON m.id = r.ministry_id
              WHERE r.created_at > DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY m.id, m.name ORDER BY qty DESC LIMIT 8',
            ['d' => $days]
        );
    }
}
