<?php
// app/Logger.php — log técnico em arquivo e trilha de auditoria no banco
declare(strict_types=1);

final class Logger
{
    /** Campos que nunca vão para a auditoria. */
    private const SENSITIVE_KEYS = ['password', 'password_hash', 'new_password', 'current_password', 'password_confirm', '_csrf'];

    public static function error(string $message, array $context = []): void
    {
        self::write('ERRO', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s %s | ip=%s uri=%s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $_SERVER['REQUEST_URI'] ?? '-',
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        if (!is_dir(LOG_PATH)) {
            @mkdir(LOG_PATH, 0750, true);
        }
        @file_put_contents(LOG_PATH . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Registra uma ação na auditoria. Nunca interrompe o fluxo: se falhar, vai para o log em arquivo.
     */
    public static function audit(string $action, string $entity, int|string|null $entityId = null, ?array $before = null, ?array $after = null, ?int $userId = null): void
    {
        try {
            Database::run(
                'INSERT INTO audit_log (user_id, action, entity, entity_id, before_data, after_data, ip, user_agent)
                 VALUES (:user_id, :action, :entity, :entity_id, :before_data, :after_data, :ip, :user_agent)',
                [
                    'user_id'     => $userId ?? Auth::id(),
                    'action'      => $action,
                    'entity'      => $entity,
                    'entity_id'   => $entityId === null ? null : (string) $entityId,
                    'before_data' => self::encode($before),
                    'after_data'  => self::encode($after),
                    'ip'          => client_ip(),
                    'user_agent'  => user_agent(),
                ]
            );
        } catch (Throwable $e) {
            self::error('Falha ao gravar auditoria: ' . $e->getMessage(), ['action' => $action, 'entity' => $entity, 'id' => $entityId]);
        }
    }

    private static function encode(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }
        foreach (self::SENSITIVE_KEYS as $key) {
            unset($data[$key]);
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
