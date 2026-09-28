<?php
// app/bootstrap.php — carrega configuração, classes e tratamento de erros
declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('Este sistema requer PHP 8.2 ou superior. Ajuste em cPanel → Selecionar versão do PHP.');
}

require APP_ROOT . '/app/config.php';
require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/Database.php';
require APP_ROOT . '/app/Logger.php';
require APP_ROOT . '/app/Csrf.php';
require APP_ROOT . '/app/Auth.php';
require APP_ROOT . '/app/Validator.php';
require APP_ROOT . '/app/Router.php';

spl_autoload_register(static function (string $class): void {
    foreach (['controllers', 'models', 'services', 'storage'] as $dir) {
        $file = APP_ROOT . "/app/{$dir}/{$class}.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    Logger::error($e->getMessage(), [
        'ref'   => $ref,
        'type'  => $e::class,
        'file'  => $e->getFile() . ':' . $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Erro [{$ref}]: {$e->getMessage()}\n");
        exit(1);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $message = $e instanceof PDOException && !DB_NAME
        ? 'Banco de dados não configurado. Verifique o arquivo .env.'
        : "Ocorreu um erro inesperado. Se persistir, informe o código {$ref} à liderança da mídia.";
    if (APP_ENV === 'development') {
        $message .= ' — ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
    }
    try {
        abort(500, $message);
    } catch (Throwable) {
        http_response_code(500);
        echo e($message);
    }
});

if (PHP_SAPI !== 'cli') {
    $captcha = HCAPTCHA_ENABLED ? ' https://js.hcaptcha.com https://*.hcaptcha.com' : '';
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net{$captcha}; style-src 'self' https://cdn.jsdelivr.net{$captcha}; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'{$captcha}; frame-src 'self'{$captcha}; worker-src 'self' blob:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    Auth::startSession();
}
