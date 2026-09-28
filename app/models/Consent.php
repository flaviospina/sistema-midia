<?php
// app/models/Consent.php — registro de consentimentos (LGPD)
declare(strict_types=1);

final class Consent
{
    public const TYPES = [
        'cadastro'      => 'Cadastro e tratamento de dados',
        'uso_imagem'    => 'Uso de imagem',
        'envio_arquivo' => 'Envio de arquivo',
    ];

    public static function record(?int $userId, string $type, string $version): int
    {
        return Database::insert(
            'INSERT INTO consents (user_id, consent_type, terms_version, ip, user_agent)
             VALUES (:user_id, :type, :version, :ip, :ua)',
            ['user_id' => $userId, 'type' => $type, 'version' => $version, 'ip' => client_ip(), 'ua' => user_agent()]
        );
    }

    public static function hasActive(int $userId, string $type, string $version): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM consents
              WHERE user_id = :u AND consent_type = :t AND terms_version = :v AND revoked_at IS NULL',
            ['u' => $userId, 't' => $type, 'v' => $version]
        ) > 0;
    }

    public static function forUser(int $userId): array
    {
        return Database::all(
            'SELECT * FROM consents WHERE user_id = :u ORDER BY accepted_at DESC',
            ['u' => $userId]
        );
    }

    public static function revoke(int $userId, string $type): int
    {
        return Database::run(
            'UPDATE consents SET revoked_at = NOW(), revoked_ip = :ip
              WHERE user_id = :u AND consent_type = :t AND revoked_at IS NULL',
            ['ip' => client_ip(), 'u' => $userId, 't' => $type]
        )->rowCount();
    }
}
