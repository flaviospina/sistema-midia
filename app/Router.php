<?php
// app/Router.php — roteador simples com checagem de login, permissão e CSRF
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    /**
     * Padrões: {id} (numérico) ou {token} (letras, números, "-" e "_").
     * Opções:
     *  - auth  (bool, padrão true): exige login
     *  - perm  (string|null): permissão exigida (Auth::can)
     *  - gate  (bool, padrão true): aplica troca de senha obrigatória e aceite do termo
     *  - guest (bool): só para quem NÃO está logado (ex.: login)
     *  - csrf  (bool, padrão true): valida token CSRF em POST
     */
    public function get(string $pattern, array $handler, array $options = []): self
    {
        return $this->add('GET', $pattern, $handler, $options);
    }

    public function post(string $pattern, array $handler, array $options = []): self
    {
        return $this->add('POST', $pattern, $handler, $options);
    }

    private function add(string $method, string $pattern, array $handler, array $options): self
    {
        $regex = preg_replace_callback(
            '#\{(\w+)\}#',
            static fn(array $m): string => $m[1] === 'token'
                ? '(?P<token>[A-Za-z0-9_-]{8,128})'
                : '(?P<' . $m[1] . '>[0-9]+)',
            rtrim($pattern, '/') ?: '/'
        );
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'handler' => $handler, 'options' => $options];
        return $this;
    }

    public function dispatch(): never
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $this->currentPath();
        $allowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $allowed = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = [];
            foreach ($m as $key => $value) {
                if (is_string($key)) {
                    $params[] = ctype_digit($value) && $key !== 'token' ? (int) $value : $value;
                }
            }
            $this->runMiddleware($route['options'], $method, $path);

            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            exit;
        }

        abort($allowed ? 405 : 404, $allowed ? 'Método não permitido.' : '');
    }

    private function currentPath(): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rawurldecode($path);
        if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
            $path = substr($path, strlen(BASE_PATH));
        }
        $path = preg_replace('#^/(public/)?index\.php#', '', $path) ?? $path;
        $path = preg_replace('#^/enviar\.php$#', '/enviar', $path) ?? $path;
        return '/' . trim($path, '/');
    }

    private function runMiddleware(array $options, string $method, string $path): void
    {
        if ($method === 'POST' && ($options['csrf'] ?? true) && !Csrf::verify()) {
            Logger::info('Token CSRF inválido', ['path' => $path]);
            if (wants_json()) {
                json_response(false, null, 'Sua sessão expirou. Recarregue a página.', 419);
            }
            flash('warning', 'O formulário expirou. Tente novamente.');
            redirect_back($path);
        }

        if (!empty($options['guest'])) {
            if (Auth::check()) {
                redirect('/');
            }
            return;
        }

        if (($options['auth'] ?? true) === false) {
            return;
        }

        $user = Auth::user();
        if ($user === null) {
            if (wants_json()) {
                json_response(false, null, 'Faça login para continuar.', 401);
            }
            $back = $method === 'GET' && $path !== '/' ? ['voltar' => $path] : [];
            redirect('/login', $back);
        }

        if (($options['gate'] ?? true) === true) {
            if ((int) $user['must_change_password'] === 1) {
                if (wants_json()) {
                    json_response(false, null, 'Defina sua nova senha antes de continuar.', 403);
                }
                redirect('/trocar-senha');
            }
            if (!Consent::hasActive((int) $user['id'], 'cadastro', TERMS_VERSION)) {
                if (wants_json()) {
                    json_response(false, null, 'Aceite o termo de uso antes de continuar.', 403);
                }
                redirect('/termo');
            }
        }

        if (!empty($options['perm']) && !Auth::can($options['perm'])) {
            Logger::audit('acesso_negado', 'rota', null, null, ['path' => $path, 'perm' => $options['perm']]);
            abort(403);
        }
    }
}
