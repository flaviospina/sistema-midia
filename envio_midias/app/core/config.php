<?php
// app/core/config.php — lê o .env e define as constantes do sistema
declare(strict_types=1);

(static function (): void {
    $envFile = APP_ROOT . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $len = strlen($value);
        if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
    }
})();

function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? '';
    return $value === '' ? $default : $value;
}

function env_int(string $key, int $default): int
{
    $value = env($key);
    return ctype_digit($value) ? (int) $value : $default;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = strtolower(env($key));
    if ($value === '') {
        return $default;
    }
    return in_array($value, ['1', 'true', 'sim', 'yes', 'on'], true);
}

define('APP_VERSION', '1.0.0');
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_NAME', env('APP_NAME', 'Envio de Mídias ADMoema'));
define('BASE_URL', rtrim(env('BASE_URL'), '/'));
// Chave de 32 bytes em hexadecimal (64 caracteres) usada para cifrar os tokens das redes sociais
define('APP_KEY', $_ENV['APP_KEY'] ?? '');

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('SESSION_NAME', env('SESSION_NAME', 'ENVIO_SID'));
define('SESSION_COOKIE_PATH', env('SESSION_COOKIE_PATH', '/'));
define('SESSION_COOKIE_DOMAIN', env('SESSION_COOKIE_DOMAIN'));
define('SESSION_IDLE_MINUTES', env_int('SESSION_IDLE_MINUTES', 60));
define('SESSION_ABSOLUTE_HOURS', env_int('SESSION_ABSOLUTE_HOURS', 12));

// Login: tentativas, bloqueio e 2ª etapa por e-mail
define('LOGIN_MAX_ATTEMPTS', env_int('LOGIN_MAX_ATTEMPTS', 5));
define('LOGIN_WINDOW_MINUTES', env_int('LOGIN_WINDOW_MINUTES', 15));
define('LOGIN_IP_MAX_PER_HOUR', env_int('LOGIN_IP_MAX_PER_HOUR', 30));
define('TWO_FACTOR_MODE', in_array(env('TWO_FACTOR_MODE', 'opcional'), ['desligado', 'opcional', 'equipe', 'todos'], true) ? env('TWO_FACTOR_MODE', 'opcional') : 'opcional');
define('TWO_FACTOR_CODE_MINUTES', env_int('TWO_FACTOR_CODE_MINUTES', 10));
define('TRUSTED_DEVICE_DAYS', env_int('TRUSTED_DEVICE_DAYS', 30));
define('PASSWORD_RESET_MINUTES', env_int('PASSWORD_RESET_MINUTES', 60));

define('STORAGE_PATH', rtrim(env('STORAGE_PATH', APP_ROOT . '/storage'), '/'));
define('ALBUMS_PATH', STORAGE_PATH . '/albums');
define('THUMBS_PATH', STORAGE_PATH . '/thumbs');
define('TMP_PATH', STORAGE_PATH . '/tmp');
define('LOG_PATH', STORAGE_PATH . '/logs');

// Upload em pedaços
define('UPLOAD_CHUNK_BYTES', env_int('UPLOAD_CHUNK_MB', 2) * 1024 * 1024);
define('UPLOAD_MAX_BYTES', env_int('UPLOAD_MAX_MB', 80) * 1024 * 1024);
define('UPLOAD_SESSION_HOURS', env_int('UPLOAD_SESSION_HOURS', 24));
define('DISPLAY_MAX_SIDE', env_int('DISPLAY_MAX_SIDE', 2048));
define('THUMB_SIDE', 480);
define('STORAGE_ALERT_GB', env_int('STORAGE_ALERT_GB', 40));

// Redes sociais (Meta Graph API) e link público temporário da foto
define('META_GRAPH_VERSION', env('META_GRAPH_VERSION', 'v21.0'));
define('PUBLIC_LINK_MINUTES', env_int('PUBLIC_LINK_MINUTES', 30));

// E-mail
define('MAIL_DRIVER', in_array(env('MAIL_DRIVER', 'mail'), ['mail', 'smtp', 'log'], true) ? env('MAIL_DRIVER', 'mail') : 'mail');
define('MAIL_FROM', env('MAIL_FROM', 'no-reply@localhost'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', APP_NAME));
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', env_int('SMTP_PORT', 465));
define('SMTP_ENCRYPTION', in_array(env('SMTP_ENCRYPTION', 'ssl'), ['ssl', 'tls', 'none'], true) ? env('SMTP_ENCRYPTION', 'ssl') : 'ssl');
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SUPPORT_CONTACT', env('SUPPORT_CONTACT', 'midia@admoema.com.br'));

// Caminho base das URLs (ex.: "/envio_midias"). Vem do BASE_URL; se vazio, é deduzido da pasta do index.php.
define('BASE_PATH', (static function (): string {
    if (BASE_URL !== '') {
        return rtrim((string) parse_url(BASE_URL, PHP_URL_PATH), '/');
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim((string) $dir, '/');
})());

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php-error.log');
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
