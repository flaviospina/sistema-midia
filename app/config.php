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

function env_bool(string $key, bool $default = false): bool
{
    $value = strtolower(env($key));
    if ($value === '') {
        return $default;
    }
    return in_array($value, ['1', 'true', 'sim', 'yes', 'on'], true);
}

define('APP_VERSION', '4.0.0');
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

// Repositório de arquivos
define('STORAGE_DRIVER', env('STORAGE_DRIVER', 'local'));
define('UPLOAD_CHUNK_BYTES', env_int('UPLOAD_CHUNK_MB', 5) * 1024 * 1024);
define('UPLOAD_MAX_BYTES', env_int('UPLOAD_MAX_MB', 2048) * 1024 * 1024);
define('UPLOAD_SESSION_HOURS', env_int('UPLOAD_SESSION_HOURS', 24));
define('QUOTA_GB', [
    'admin'            => env_int('QUOTA_GB_ADMIN', 0),
    'coordenador'      => env_int('QUOTA_GB_COORDENADOR', 20),
    'membro_midia'     => env_int('QUOTA_GB_MEMBRO_MIDIA', 20),
    'lider_ministerio' => env_int('QUOTA_GB_LIDER_MINISTERIO', 2),
    'pastor'           => env_int('QUOTA_GB_PASTOR', 2),
    'membro_igreja'    => env_int('QUOTA_GB_MEMBRO_IGREJA', 2),
]);
define('STORAGE_ALERT_GB', env_int('STORAGE_ALERT_GB', 40));
define('GUEST_MAX_FILES_PER_HOUR', env_int('GUEST_MAX_FILES_PER_HOUR', 30));
define('GUEST_MAX_MB_PER_DAY', env_int('GUEST_MAX_MB_PER_DAY', 500));
define('GUEST_MAX_FILE_BYTES', env_int('GUEST_MAX_FILE_MB', 500) * 1024 * 1024);
define('HCAPTCHA_ENABLED', env_bool('HCAPTCHA_ENABLED') && env('HCAPTCHA_SITE_KEY') !== '' && env('HCAPTCHA_SECRET') !== '');
define('HCAPTCHA_SITE_KEY', env('HCAPTCHA_SITE_KEY'));
define('HCAPTCHA_SECRET', env('HCAPTCHA_SECRET'));
define('RETENTION_REJECTED_DAYS', env_int('RETENTION_REJECTED_DAYS', 7));
define('RETENTION_TRASH_DAYS', env_int('RETENTION_TRASH_DAYS', 30));

// Eventos e escala
define('SCHEDULE_WEEKS_AHEAD', env_int('SCHEDULE_WEEKS_AHEAD', 8));
define('SCHEDULE_OVERLOAD_PER_MONTH', env_int('SCHEDULE_OVERLOAD_PER_MONTH', 4));
define('SCHEDULE_ROTATION_DAYS', env_int('SCHEDULE_ROTATION_DAYS', 60));

// Pedidos de arte
define('ART_MIN_DAYS', env_int('ART_MIN_DAYS', 10));
define('ART_PASTORAL_FORMATS', array_values(array_filter(array_map('trim', explode(',', env('ART_PASTORAL_FORMATS', 'impresso,telao'))))));
define('ART_FOLDER_NAME', 'Pedidos de arte');

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
