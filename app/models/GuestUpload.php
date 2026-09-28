<?php
// app/models/GuestUpload.php — envios pela página pública /enviar
declare(strict_types=1);

final class GuestUpload
{
    public static function create(array $d): array
    {
        $token = bin2hex(random_bytes(16));
        $id = Database::insert(
            'INSERT INTO guest_uploads (guest_name, whatsapp, ministry_id, ministry_name, event_name, description, terms_version, ip, user_agent, token)
             VALUES (:guest_name, :whatsapp, :ministry_id, :ministry_name, :event_name, :description, :terms_version, :ip, :user_agent, :token)',
            $d + ['terms_version' => TERMS_VERSION, 'ip' => client_ip(), 'user_agent' => user_agent(), 'token' => $token]
        );
        return ['id' => $id, 'token' => $token];
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM guest_uploads WHERE id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return Database::one('SELECT * FROM guest_uploads WHERE token = :t AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)', ['t' => $token]);
    }

    /** Arquivos enviados a partir deste IP na última hora. */
    public static function filesLastHour(string $ip): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM files WHERE guest_upload_id IS NOT NULL AND upload_ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            ['ip' => $ip]
        );
    }

    /** Bytes enviados a partir deste IP nas últimas 24 h (concluídos + em andamento). */
    public static function bytesLastDay(string $ip): int
    {
        $done = (int) Database::value(
            'SELECT COALESCE(SUM(size_bytes),0) FROM files WHERE guest_upload_id IS NOT NULL AND upload_ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)',
            ['ip' => $ip]
        );
        $pending = (int) Database::value(
            "SELECT COALESCE(SUM(size_bytes),0) FROM upload_sessions WHERE guest_upload_id IS NOT NULL AND ip = :ip AND status IN ('aberto','montado','duplicado') AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            ['ip' => $ip]
        );
        return $done + $pending;
    }
}
