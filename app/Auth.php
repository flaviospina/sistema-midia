<?php
// app/Auth.php — sessão, login e permissões
declare(strict_types=1);

final class Auth
{
    public const ROLES = [
        'admin'            => 'Administrador (líder da mídia)',
        'coordenador'      => 'Coordenador de área',
        'membro_midia'     => 'Membro da mídia',
        'lider_ministerio' => 'Líder de ministério',
        'pastor'           => 'Pastor',
        'membro_igreja'    => 'Membro da igreja',
    ];

    /** Perfis que fazem parte da equipe de mídia (têm ficha em media_members). */
    public const MEDIA_ROLES = ['admin', 'coordenador', 'membro_midia'];

    /** Matriz de permissões. Checada no servidor em toda rota protegida. */
    private const PERMISSIONS = [
        'users.view'          => ['admin', 'coordenador', 'pastor'],
        'users.manage'        => ['admin'],
        'users.functions'     => ['admin', 'coordenador'],
        'users.approve'       => ['admin', 'coordenador'],
        'ministries.view'     => ['admin', 'coordenador', 'pastor', 'membro_midia'],
        'ministries.manage'   => ['admin'],
        'functions.manage'    => ['admin'],
        'audit.view'          => ['admin'],
        'privacy.manage'      => ['admin'],
        // Repositório
        'files.browse'        => ['admin', 'coordenador', 'membro_midia', 'lider_ministerio', 'pastor', 'membro_igreja'],
        'files.upload'        => ['admin', 'coordenador', 'membro_midia', 'lider_ministerio', 'pastor', 'membro_igreja'],
        'files.moderate'      => ['admin', 'coordenador'],
        'files.share'         => ['admin', 'coordenador', 'membro_midia'],
        'files.original'      => ['admin'],
        'folders.manage'      => ['admin', 'coordenador'],
        'storage.view'        => ['admin', 'coordenador'],
        'restrictions.view'   => ['admin', 'coordenador', 'membro_midia'],
        'restrictions.manage' => ['admin', 'coordenador'],
        // Eventos e escala
        'events.view'         => ['admin', 'coordenador', 'membro_midia', 'lider_ministerio', 'pastor', 'membro_igreja'],
        'events.manage'       => ['admin', 'coordenador'],
        'schedule.view'       => ['admin', 'coordenador', 'membro_midia', 'pastor'],
        'schedule.manage'     => ['admin', 'coordenador'],
        'schedule.self'       => ['admin', 'coordenador', 'membro_midia'],
        'unavailability.view' => ['admin', 'coordenador'],
        // Pedidos de arte e comunicação
        'art.request'         => ['admin', 'coordenador', 'membro_midia', 'lider_ministerio', 'pastor'],
        'art.produce'         => ['admin', 'coordenador', 'membro_midia'],
        'art.manage'          => ['admin', 'coordenador'],
        'art.approve_media'   => ['admin', 'coordenador'],
        'art.approve_pastoral'=> ['admin', 'pastor'],
        'art.view_all'        => ['admin', 'coordenador', 'membro_midia', 'pastor'],
        'publications.view'   => ['admin', 'coordenador', 'membro_midia', 'pastor', 'lider_ministerio'],
        'publications.manage' => ['admin', 'coordenador', 'membro_midia'],
        // Integrações
        'integrations.manage' => ['admin'],
        // Patrimônio, checklist, ocorrências, capacitação, painel do líder
        'equipment.view'      => ['admin', 'coordenador', 'membro_midia'],
        'equipment.manage'    => ['admin', 'coordenador'],
        'checklist.fill'      => ['admin', 'coordenador', 'membro_midia'],
        'checklist.manage'    => ['admin', 'coordenador'],
        'incidents.report'    => ['admin', 'coordenador', 'membro_midia'],
        'incidents.manage'    => ['admin', 'coordenador'],
        'reports.fill'        => ['admin', 'coordenador', 'membro_midia'],
        'training.view'       => ['admin', 'coordenador', 'membro_midia'],
        'training.manage'     => ['admin', 'coordenador'],
        'leader.dashboard'    => ['admin', 'coordenador', 'pastor'],
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || str_starts_with(BASE_URL, 'https://');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (SESSION_IDLE_MINUTES * 60));
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => SESSION_COOKIE_PATH,
            'domain'   => SESSION_COOKIE_DOMAIN,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Expiração por inatividade
        $now = time();
        if (isset($_SESSION['uid'], $_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > SESSION_IDLE_MINUTES * 60) {
            self::logout();
            session_start();
            flash('warning', 'Sua sessão expirou por inatividade. Entre novamente.');
        }
        $_SESSION['last_seen'] = $now;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['sv'] = (int) $user['session_version'];
        $_SESSION['last_seen'] = time();
        unset($_SESSION['_csrf']);
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /**
     * Usuário logado, recarregado do banco a cada requisição: se ele for desativado,
     * trocar de senha em outro aparelho ou perder o perfil, a sessão cai na hora.
     */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $user = User::findForAuth((int) $_SESSION['uid']);
        if (!$user || $user['status'] !== 'ativo' || (int) $user['session_version'] !== (int) ($_SESSION['sv'] ?? 0) || empty($user['role'])) {
            self::logout();
            session_start();
            return null;
        }
        self::$user = $user;
        return $user;
    }

    /** Força recarregar o usuário do banco (após alterar os próprios dados). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function isMedia(): bool
    {
        return self::is(...self::MEDIA_ROLES);
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }
        return in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLES[$role] ?? '—';
    }

    /**
     * Funções que o usuário logado pode escalar: admin todas; coordenador só as áreas que coordena
     * (se não coordenar nenhuma, todas). Devolve null para "todas".
     */
    public static function coordinatedFunctionIds(): ?array
    {
        static $ids = false;
        if ($ids === false) {
            if (self::is('admin')) {
                $ids = null;
            } elseif (self::is('coordenador')) {
                $list = array_map('intval', array_column(Database::all(
                    'SELECT function_id FROM member_functions WHERE user_id = :u AND is_coordinator = 1', ['u' => self::id()]
                ), 'function_id'));
                $ids = $list ?: null;
            } else {
                $ids = [];
            }
        }
        return $ids;
    }

    public static function canScheduleFunction(int $functionId): bool
    {
        if (!self::can('schedule.manage')) {
            return false;
        }
        $ids = self::coordinatedFunctionIds();
        return $ids === null || in_array($functionId, $ids, true);
    }

    /** IDs dos ministérios do usuário logado (cache por requisição). */
    public static function ministryIds(): array
    {
        static $ids = null;
        if ($ids === null) {
            $id = self::id();
            $ids = $id ? array_map('intval', array_column(User::ministries($id), 'id')) : [];
        }
        return $ids;
    }
}
