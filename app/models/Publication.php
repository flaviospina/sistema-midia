<?php
// app/models/Publication.php — calendário de comunicação (agenda de postagens)
declare(strict_types=1);

final class Publication
{
    public const CHANNELS = [
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'youtube'   => 'YouTube',
        'whatsapp'  => 'WhatsApp',
        'telao'     => 'Telão',
        'boletim'   => 'Boletim',
        'site'      => 'Site',
        'outro'     => 'Outro',
    ];

    public const CHANNEL_ICONS = [
        'instagram' => 'bi-instagram', 'facebook' => 'bi-facebook', 'youtube' => 'bi-youtube', 'whatsapp' => 'bi-whatsapp',
        'telao' => 'bi-display', 'boletim' => 'bi-newspaper', 'site' => 'bi-globe', 'outro' => 'bi-megaphone',
    ];

    public const STATUSES = ['planejado' => 'Planejado', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado'];

    /** Formato da arte → canal padrão da agenda. */
    public const FORMAT_CHANNEL = ['story' => 'instagram', 'feed' => 'instagram', 'telao' => 'telao', 'impresso' => 'boletim'];

    private const SELECT = 'SELECT p.*, r.title AS request_title, r.status AS request_status, r.ministry_id, e.title AS event_title,
                                   u.name AS responsible_name, f.thumb_ref, f.original_name
                              FROM publications p
                         LEFT JOIN art_requests r ON r.id = p.request_id
                         LEFT JOIN events e ON e.id = p.event_id
                         LEFT JOIN users u ON u.id = p.responsible_id
                         LEFT JOIN files f ON f.id = p.file_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE p.id = :id', ['id' => $id]);
    }

    public static function between(string $from, string $to, string $channel = ''): array
    {
        $sql = self::SELECT . ' WHERE p.publish_at >= :from AND p.publish_at < :to';
        $params = ['from' => $from, 'to' => $to];
        if ($channel !== '') {
            $sql .= ' AND p.channel = :ch';
            $params['ch'] = $channel;
        }
        if (!Auth::can('publications.manage')) {
            // Líder de ministério vê só o que é do seu ministério
            $ids = Auth::ministryIds();
            $sql .= ' AND r.ministry_id IN (' . ($ids ? implode(',', $ids) : '0') . ')';
        }
        return Database::all($sql . ' ORDER BY p.publish_at', $params);
    }

    public static function forRequest(int $requestId): array
    {
        return Database::all(self::SELECT . ' WHERE p.request_id = :r ORDER BY p.publish_at', ['r' => $requestId]);
    }

    public static function upcoming(int $days = 7): array
    {
        return Database::all(self::SELECT . " WHERE p.status = 'planejado' AND p.publish_at < DATE_ADD(NOW(), INTERVAL :d DAY) ORDER BY p.publish_at LIMIT 10", ['d' => $days]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO publications (title, channel, publish_at, request_id, event_id, file_id, responsible_id, notes, created_by)
             VALUES (:title, :channel, :publish_at, :request_id, :event_id, :file_id, :responsible_id, :notes, :by)',
            $d + ['by' => Auth::id()]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE publications SET title = :title, channel = :channel, publish_at = :publish_at, event_id = :event_id, file_id = :file_id,
                    responsible_id = :responsible_id, notes = :notes WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function markPublished(int $id, ?string $link): void
    {
        Database::run("UPDATE publications SET status = 'publicado', published_at = NOW(), published_by = :u, link = :l WHERE id = :id", ['u' => Auth::id(), 'l' => $link, 'id' => $id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE publications SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    /** Cria as entradas da agenda para um pedido aprovado (uma por formato), se ainda não existirem. */
    public static function createForRequest(array $r, ?int $fileId): int
    {
        $n = 0;
        foreach (ArtRequest::formatsOf($r) as $format) {
            $channel = self::FORMAT_CHANNEL[$format] ?? 'outro';
            $exists = Database::value('SELECT id FROM publications WHERE request_id = :r AND channel = :c AND status <> "cancelado"', ['r' => $r['id'], 'c' => $channel]);
            if ($exists) {
                continue;
            }
            self::create([
                'title' => $r['title'] . ' — ' . (ArtRequest::FORMATS[$format] ?? $format), 'channel' => $channel,
                'publish_at' => $r['publish_on'] . ' 09:00:00', 'request_id' => (int) $r['id'], 'event_id' => $r['event_id'] ? (int) $r['event_id'] : null,
                'file_id' => $fileId, 'responsible_id' => $r['designer_id'] ? (int) $r['designer_id'] : null, 'notes' => null,
            ]);
            $n++;
        }
        return $n;
    }

    /** Todas as entradas do pedido publicadas? */
    public static function allPublished(int $requestId): bool
    {
        $total = (int) Database::value("SELECT COUNT(*) FROM publications WHERE request_id = :r AND status <> 'cancelado'", ['r' => $requestId]);
        $done = (int) Database::value("SELECT COUNT(*) FROM publications WHERE request_id = :r AND status = 'publicado'", ['r' => $requestId]);
        return $total > 0 && $total === $done;
    }
}
