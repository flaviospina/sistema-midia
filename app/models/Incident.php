<?php
// app/models/Incident.php — ocorrências e relatório pós-culto
declare(strict_types=1);

final class Incident
{
    public const KINDS = ['tecnico' => 'Técnico', 'equipamento' => 'Equipamento', 'transmissao' => 'Transmissão', 'pessoal' => 'Pessoal / escala', 'outro' => 'Outro'];
    public const SEVERITIES = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'];
    public const STATUSES = ['aberta' => 'Aberta', 'em_andamento' => 'Em andamento', 'resolvida' => 'Resolvida'];
    public const SEVERITY_COLORS = ['baixa' => 'secondary', 'media' => 'warning', 'alta' => 'danger'];

    private const SELECT = 'SELECT i.*, e.title AS event_title, e.starts_at, q.code AS equipment_code, q.name AS equipment_name,
                                   r.name AS reporter_name, s.name AS resolver_name
                              FROM incidents i LEFT JOIN events e ON e.id = i.event_id LEFT JOIN equipment q ON q.id = i.equipment_id
                         LEFT JOIN users r ON r.id = i.reported_by LEFT JOIN users s ON s.id = i.resolved_by';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE i.id = :id', ['id' => $id]);
    }

    public static function search(array $f, int $page, int $perPage = 30): array
    {
        $conds = ['1=1'];
        $params = [];
        if (($f['status'] ?? '') !== '') {
            $conds[] = 'i.status = :st';
            $params['st'] = $f['status'];
        } elseif (empty($f['all'])) {
            $conds[] = "i.status <> 'resolvida'";
        }
        if (($f['kind'] ?? '') !== '') {
            $conds[] = 'i.kind = :k';
            $params['k'] = $f['kind'];
        }
        if (($f['event_id'] ?? '') !== '') {
            $conds[] = 'i.event_id = :ev';
            $params['ev'] = (int) $f['event_id'];
        }
        $where = implode(' AND ', $conds);
        $total = (int) Database::value("SELECT COUNT(*) FROM incidents i WHERE {$where}", $params);
        $pg = paginate($total, $page, $perPage);
        return ['itens' => Database::all(self::SELECT . " WHERE {$where} ORDER BY FIELD(i.status,'aberta','em_andamento','resolvida'), FIELD(i.severity,'alta','media','baixa'), i.id DESC LIMIT :limit OFFSET :offset", $params + ['limit' => $perPage, 'offset' => $pg['offset']])] + $pg;
    }

    public static function forEvent(int $eventId): array
    {
        return Database::all(self::SELECT . ' WHERE i.event_id = :e ORDER BY i.id DESC', ['e' => $eventId]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO incidents (event_id, equipment_id, kind, severity, title, description, reported_by) VALUES (:event_id, :equipment_id, :kind, :severity, :title, :description, :by)',
            $d + ['by' => Auth::id()]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run('UPDATE incidents SET event_id = :event_id, equipment_id = :equipment_id, kind = :kind, severity = :severity, title = :title, description = :description WHERE id = :id', $d + ['id' => $id]);
    }

    public static function setStatus(int $id, string $status, ?string $resolution): void
    {
        Database::run(
            "UPDATE incidents SET status = :s, resolution = COALESCE(:r, resolution), resolved_at = CASE WHEN :s2 = 'resolvida' THEN NOW() ELSE NULL END, resolved_by = CASE WHEN :s3 = 'resolvida' THEN :u ELSE NULL END WHERE id = :id",
            ['s' => $status, 'r' => $resolution, 's2' => $status, 's3' => $status, 'u' => Auth::id(), 'id' => $id]
        );
    }

    public static function countOpen(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM incidents WHERE status <> 'resolvida'");
    }

    public static function statsByKind(int $days = 180): array
    {
        return Database::all('SELECT kind, COUNT(*) n FROM incidents WHERE created_at > DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY kind ORDER BY n DESC', ['d' => $days]);
    }

    // ---- Relatório pós-culto ------------------------------------------

    public static function report(int $eventId): ?array
    {
        return Database::one('SELECT r.*, u.name AS filled_by_name FROM event_reports r LEFT JOIN users u ON u.id = r.filled_by WHERE r.event_id = :e', ['e' => $eventId]);
    }

    public static function saveReport(int $eventId, array $d): void
    {
        Database::run(
            'INSERT INTO event_reports (event_id, filled_by, live_platform, live_peak, live_average, live_total_views, attendance_estimate, summary, highlights, improvements)
             VALUES (:e, :by, :live_platform, :live_peak, :live_average, :live_total_views, :attendance_estimate, :summary, :highlights, :improvements)
             ON DUPLICATE KEY UPDATE filled_by = VALUES(filled_by), live_platform = VALUES(live_platform), live_peak = VALUES(live_peak), live_average = VALUES(live_average),
                     live_total_views = VALUES(live_total_views), attendance_estimate = VALUES(attendance_estimate), summary = VALUES(summary), highlights = VALUES(highlights), improvements = VALUES(improvements)',
            $d + ['e' => $eventId, 'by' => Auth::id()]
        );
    }

    /** Audiência por evento (últimos N) para o painel. */
    public static function audienceSeries(int $limit = 12): array
    {
        return array_reverse(Database::all(
            'SELECT e.title, e.starts_at, r.live_peak, r.live_average, r.attendance_estimate FROM event_reports r JOIN events e ON e.id = r.event_id ORDER BY e.starts_at DESC LIMIT :l',
            ['l' => $limit]
        ));
    }

    public static function eventsWithoutReport(int $days = 14): array
    {
        return Database::all(
            "SELECT e.id, e.title, e.starts_at FROM events e LEFT JOIN event_reports r ON r.event_id = e.id
              WHERE e.status IN ('concluido','agendado') AND e.starts_at < NOW() AND e.starts_at > DATE_SUB(NOW(), INTERVAL :d DAY) AND r.event_id IS NULL AND e.event_type = 'culto'
              ORDER BY e.starts_at DESC",
            ['d' => $days]
        );
    }
}
