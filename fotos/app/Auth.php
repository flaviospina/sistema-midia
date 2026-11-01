<?php
// app/Auth.php — sessão reforçada, login em duas etapas e permissões do sistema de fotos
declare(strict_types=1);

final class Auth
{
    public const ROLES = [
        'admin'     => 'Administrador',
        'fotografo' => 'Fotógrafo(a) / equipe de mídia',
        'social'    => 'Redes sociais',
        'membro'    => 'Membro da igreja',
    ];

    /** Perfis da equipe (veem álbuns ocultos, enviam fotos). */
    public const TEAM_ROLES = ['admin', 'fotografo', 'social'];

    private const PERMISSIONS = [
        'albums.view'    => ['admin', 'fotografo', 'social', 'membro'],
        'albums.hidden'  => ['admin', 'fotografo', 'social'],
        'albums.manage'  => ['admin', 'fotografo'],
        'photos.upload'  => ['admin', 'fotografo', 'social'],
        'photos.delete'  => ['admin', 'fotografo'],
        'photos.download'=> ['admin', 'fotografo', 'social', 'membro'],
        'photos.edit'    => ['admin', 'fotografo', 'social', 'membro'],   // abrir o editor e baixar o resultado
        'photos.save_version' => ['admin', 'fotografo', 'social'],       // gravar a versão editada no álbum
        'social.share'   => ['admin', 'social'],
        'links.create'   => ['admin', 'fotografo', 'social'],              // links públicos com pré-visualização
        'users.manage'   => ['admin'],
        'audit.view'     => ['admin'],
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
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) (SESSION_IDLE_MINUTES * 60));
        session_name(SESSION_NAME);
        session_set_cookie_params(['lifetime' => 0, 'path' => SESSION_COOKIE_PATH, 'domain' => SESSION_COOKIE_DOMAIN, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();

        $now = time();
        if (isset($_SESSION['uid'])) {
            $expired = $now - (int) ($_SESSION['last_seen'] ?? $now) > SESSION_IDLE_MINUTES * 60
                || $now - (int) ($_SESSION['login_at'] ?? $now) > SESSION_ABSOLUTE_HOURS * 3600;
            // A sessão fica presa ao navegador que a criou (dificulta uso de cookie roubado)
            $hijacked = ($_SESSION['fp'] ?? '') !== self::fingerprint();
            if ($expired || $hijacked) {
                self::logout();
                session_start();
                flash('warning', $hijacked ? 'Por segurança, entre novamente.' : 'Sua sessão expirou. Entre novamente.');
            }
        }
        $_SESSION['last_seen'] = $now;
        // Renova o ID periodicamente para reduzir a janela de um ID vazado
        if (isset($_SESSION['uid']) && $now - (int) ($_SESSION['rotated_at'] ?? 0) > 900) {
            session_regenerate_id(true);
            $_SESSION['rotated_at'] = $now;
        }
    }

    private static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = ['uid' => (int) $user['id'], 'sv' => (int) $user['session_version'], 'last_seen' => time(), 'login_at' => time(), 'rotated_at' => time(), 'fp' => self::fingerprint()];
        self::$user = null;
        self::$loaded = false;
    }

    /** Guarda o usuário que passou pela senha e aguarda o código enviado por e-mail (2ª etapa). */
    public static function startPending(int $userId, string $back = ''): void
    {
        session_regenerate_id(true);
        $_SESSION['pending_uid'] = $userId;
        $_SESSION['pending_at'] = time();
        $_SESSION['pending_back'] = $back;
    }

    public static function pendingUserId(): ?int
    {
        if (empty($_SESSION['pending_uid']) || time() - (int) ($_SESSION['pending_at'] ?? 0) > TWO_FACTOR_CODE_MINUTES * 60) {
            return null;
        }
        return (int) $_SESSION['pending_uid'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax']);
        }
        session_destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** Usuário logado, recarregado do banco a cada requisição (desativação ou troca de senha derruba a sessão). */
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
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function isTeam(): bool
    {
        return self::is(...self::TEAM_ROLES);
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLES[$role] ?? '—';
    }

    /** Se a 2ª etapa por e-mail se aplica a este usuário (conforme TWO_FACTOR_MODE e preferência pessoal). */
    public static function twoFactorRequired(array $user): bool
    {
        return match (TWO_FACTOR_MODE) {
            'desligado' => false,
            'todos'     => true,
            'equipe'    => in_array($user['role'], self::TEAM_ROLES, true) || User::pref((int) $user['id'], 'two_factor') === '1',
            default     => User::pref((int) $user['id'], 'two_factor') === '1',
        };
    }
}
