<?php
// app/helpers.php — funções utilitárias usadas em controllers e views
declare(strict_types=1);

/** Escapa qualquer valor para saída HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $url = BASE_PATH . '/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}

/** URL absoluta (esquema + host + BASE_PATH + caminho) para e-mails, ICS, links e avisos. */
function absolute_url(string $path = '/', array $query = []): string
{
    $origin = BASE_URL !== '' ? preg_replace('#^(https?://[^/]+).*$#', '$1', BASE_URL) : '';
    if ($origin === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $origin . url($path, $query);
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . APP_VERSION;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

function redirect_back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    if ($ref !== '' && $host === ($_SERVER['HTTP_HOST'] ?? null)) {
        header('Location: ' . $ref, true, 303);
        exit;
    }
    redirect($fallback);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

/** Guarda a entrada do formulário e os erros para reexibir após o redirect. */
function with_errors(array $errors, array $input = []): void
{
    $_SESSION['_errors'] = $errors;
    $_SESSION['_old'] = $input;
}

function errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
    }
    return $errors;
}

function old(string $key, mixed $default = ''): mixed
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
    return $old[$key] ?? $default;
}

function field_error(string $key): string
{
    $errors = errors();
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}

function invalid(string $key): string
{
    return isset(errors()[$key]) ? ' is-invalid' : '';
}

/** Lê um campo do POST como texto aparado. */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function json_response(bool $ok, mixed $data = null, string $message = '', int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'data' => $data, 'mensagem' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Renderiza uma view dentro do layout. */
function view(string $name, array $data = [], ?string $layout = 'layout'): never
{
    echo render($name, $data, $layout);
    exit;
}

function render(string $name, array $data = [], ?string $layout = 'layout'): string
{
    $file = APP_ROOT . '/app/views/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("View não encontrada: {$name}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $content = ob_get_clean();
    if ($layout === null) {
        return $content;
    }
    ob_start();
    require APP_ROOT . '/app/views/' . $layout . '.php';
    return ob_get_clean();
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/views/partials/' . $name . '.php';
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    if (wants_json()) {
        json_response(false, null, $message ?: 'Não foi possível concluir a solicitação.', $status);
    }
    $view = in_array($status, [403, 404, 419, 429, 500], true) ? (string) $status : '500';
    $title = match ($status) {
        403 => 'Acesso negado',
        404 => 'Página não encontrada',
        419 => 'Sessão expirada',
        429 => 'Muitas tentativas',
        default => 'Erro no sistema',
    };
    echo render('errors/' . $view, ['message' => $message, 'title' => $title], 'layout');
    exit;
}

function format_date(?string $value, string $format = 'd/m/Y'): string
{
    if (!$value) {
        return '—';
    }
    try {
        return (new DateTime($value))->format($format);
    } catch (Throwable) {
        return '—';
    }
}

function format_datetime(?string $value): string
{
    return format_date($value, 'd/m/Y H:i');
}

/** Formata WhatsApp armazenado só com dígitos (ex.: 5511999998888). */
function format_phone(?string $digits): string
{
    if (!$digits) {
        return '—';
    }
    if (preg_match('/^55(\d{2})(\d{4,5})(\d{4})$/', $digits, $m)) {
        return "({$m[1]}) {$m[2]}-{$m[3]}";
    }
    return $digits;
}

function whatsapp_link(?string $digits): string
{
    return $digits ? 'https://wa.me/' . rawurlencode($digits) : '#';
}

/** Página atual para listagens (1-based). */
function current_page(): int
{
    $page = (int) query('pagina', '1');
    return max(1, $page);
}

/** Monta o array de paginação padrão. */
function paginate(int $total, int $page, int $perPage = 25): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return [
        'total'      => $total,
        'pagina'     => $page,
        'por_pagina' => $perPage,
        'paginas'    => $pages,
        'offset'     => ($page - 1) * $perPage,
    ];
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/** Gera senha temporária legível (sem caracteres ambíguos). */
function temp_password(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/** 1536000 → "1,5 MB". */
function format_bytes(int|float $bytes, int $decimals = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < count($units) - 1) {
        $n /= 1024;
        $i++;
    }
    return number_format($n, $i === 0 ? 0 : $decimals, ',', '.') . ' ' . $units[$i];
}

/** 125 → "2:05". */
function format_duration(?int $seconds): string
{
    if ($seconds === null) {
        return '';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function truncate(string $text, int $max = 60): string
{
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
}

/** Lê o corpo JSON de uma requisição (APIs internas). */
function json_input(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];
    }
    return $data;
}
