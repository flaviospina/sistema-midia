<?php
// app/config.php — lê o .env e define as constantes do sistema
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

define('APP_VERSION', '1.0.0');
define('APP_CODE', 'midia'); // código deste sistema em user_roles
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_NAME', env('APP_NAME', 'Central de Mídia ADMoema'));
define('BASE_URL', rtrim(env('BASE_URL'), '/'));

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('SESSION_NAME', env('SESSION_NAME', 'ADMOEMA_SID'));
define('SESSION_COOKIE_PATH', env('SESSION_COOKIE_PATH', '/'));
define('SESSION_COOKIE_DOMAIN', env('SESSION_COOKIE_DOMAIN'));
define('SESSION_IDLE_MINUTES', env_int('SESSION_IDLE_MINUTES', 120));

define('LOGIN_MAX_ATTEMPTS', env_int('LOGIN_MAX_ATTEMPTS', 5));
define('LOGIN_WINDOW_MINUTES', env_int('LOGIN_WINDOW_MINUTES', 15));
define('SIGNUP_MAX_PER_HOUR', env_int('SIGNUP_MAX_PER_HOUR', 5));
define('PHOTO_MAX_MB', env_int('PHOTO_MAX_MB', 5));

define('TERMS_VERSION', env('TERMS_VERSION', '2026.1'));
define('DPO_CONTACT', env('DPO_CONTACT'));

define('STORAGE_PATH', rtrim(env('STORAGE_PATH', APP_ROOT . '/storage'), '/'));
define('LOG_PATH', STORAGE_PATH . '/logs');

// Caminho base das URLs (ex.: "/midia"). Vem do BASE_URL; se vazio, é deduzido do script.
define('BASE_PATH', (static function (): string {
    if (BASE_URL !== '') {
        return rtrim((string) parse_url(BASE_URL, PHP_URL_PATH), '/');
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/public$#', '', $dir);
    return rtrim((string) $dir, '/');
})());

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php-error.log');
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
