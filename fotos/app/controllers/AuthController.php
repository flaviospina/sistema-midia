<?php
// app/controllers/AuthController.php — login em duas etapas, saída e troca de senha (obrigatória no primeiro acesso)
declare(strict_types=1);

final class AuthController
{
    public function loginForm(): never
    {
        view('auth/login', ['title' => 'Entrar', 'back' => query('voltar')], 'layout_auth');
    }

    public function login(): never
    {
        $email = mb_strtolower(input('email'));
        $password = (string) ($_POST['password'] ?? '');
        $back = input('voltar');
        $key = $email . '|' . client_ip();

        // Honeypot: campo invisível que só robôs preenchem
        if (input('website') !== '') {
            Logger::audit('login_robo', 'users', null, null, ['email' => $email]);
            flash('danger', 'E-mail ou senha incorretos.');
            redirect('/login');
        }
        if ($email === '' || $password === '') {
            flash('danger', 'Informe e-mail e senha.');
            redirect('/login');
        }
        if (RateLimit::count('login', $key, LOGIN_WINDOW_MINUTES) >= LOGIN_MAX_ATTEMPTS || RateLimit::count('login_ip', client_ip(), 60) >= LOGIN_IP_MAX_PER_HOUR) {
            Logger::audit('login_bloqueado', 'users', null, null, ['email' => $email]);
            // Pequena espera dificulta ataques automatizados sem afetar quem digitou errado
            usleep(random_int(300000, 900000));
            flash('danger', 'Muitas tentativas. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos e tente novamente.');
            redirect('/login');
        }

        $user = User::findByEmail($email);
        // Verifica sempre um hash (mesmo sem usuário) para o tempo de resposta não revelar e-mails cadastrados
        $ok = password_verify($password, $user['password_hash'] ?? '$2y$12$WZpKe7TWXzatmvk2WdRme.5cnhccrP4C5kupdDWJbsnHLD.jytAwy') && $user !== null;
        if (!$ok) {
            RateLimit::hit('login', $key);
            RateLimit::hit('login_ip', client_ip());
            Logger::audit('login_falhou', 'users', $user['id'] ?? null, null, ['email' => $email]);
            with_errors([], ['email' => $email]);
            flash('danger', 'E-mail ou senha incorretos.');
            redirect('/login');
        }
        if ($user['status'] !== 'ativo' || empty($user['role'])) {
            Logger::audit('login_negado', 'users', $user['id'], null, ['status' => $user['status'], 'role' => $user['role']]);
            flash('danger', 'Este acesso não está liberado para o sistema de fotos. Fale com a equipe de mídia.');
            redirect('/login');
        }
        if (User::needsRehash($user['password_hash'])) {
            Database::run('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => User::hash($password), 'id' => $user['id']]);
        }
        RateLimit::clear('login', $key);

        // 2ª etapa por e-mail (quando se aplica e o aparelho não é confiável)
        if (Auth::twoFactorRequired($user) && !LoginCode::deviceTrusted((int) $user['id'])) {
            if (LoginCode::recentCount((int) $user['id']) >= 6) {
                flash('danger', 'Muitos códigos pedidos na última hora. Aguarde e tente de novo.');
                redirect('/login');
            }
            $code = LoginCode::issue((int) $user['id']);
            Mailer::send($user['email'], 'Seu código de acesso — ' . APP_NAME, "Olá, {$user['name']}.\n\nSeu código para entrar no " . APP_NAME . " é:\n\n    {$code}\n\nEle vale por " . TWO_FACTOR_CODE_MINUTES . " minutos. Se você não tentou entrar agora, troque sua senha.\n\nPedido feito do IP " . client_ip() . ' em ' . date('d/m/Y H:i') . '.');
            Auth::startPending((int) $user['id'], $back);
            Logger::audit('login_codigo_enviado', 'users', $user['id'], null, null, (int) $user['id']);
            redirect('/login/codigo');
        }
        $this->complete($user, $back);
    }

    public function codeForm(): never
    {
        $uid = Auth::pendingUserId();
        if (!$uid) {
            flash('warning', 'Comece de novo informando e-mail e senha.');
            redirect('/login');
        }
        $u = User::find($uid);
        view('auth/code', ['title' => 'Código de verificação', 'email' => $u ? preg_replace('/(?<=.).(?=.*@)/u', '•', $u['email']) : ''], 'layout_auth');
    }

    public function code(): never
    {
        $uid = Auth::pendingUserId();
        $user = $uid ? User::find($uid) : null;
        if (!$user) {
            flash('warning', 'O código expirou. Entre novamente.');
            redirect('/login');
        }
        if (RateLimit::count('code', (string) $uid, 15) >= 10) {
            Logger::audit('login_codigo_bloqueado', 'users', $uid);
            Auth::logout();
            session_start();
            flash('danger', 'Muitas tentativas de código. Entre novamente mais tarde.');
            redirect('/login');
        }
        if (!LoginCode::verify($uid, input('code'))) {
            RateLimit::hit('code', (string) $uid);
            Logger::audit('login_codigo_invalido', 'users', $uid);
            flash('danger', 'Código inválido ou expirado.');
            redirect('/login/codigo');
        }
        RateLimit::clear('code', (string) $uid);
        $back = (string) ($_SESSION['pending_back'] ?? '');
        if (input('trust') === '1') {
            LoginCode::trustDevice($uid);
        }
        $this->complete($user, $back);
    }

    public function resendCode(): never
    {
        $uid = Auth::pendingUserId();
        $user = $uid ? User::find($uid) : null;
        if (!$user || LoginCode::recentCount($uid) >= 6) {
            flash('warning', 'Não foi possível reenviar agora. Comece de novo em alguns minutos.');
            redirect('/login');
        }
        $code = LoginCode::issue($uid);
        Mailer::send($user['email'], 'Seu código de acesso — ' . APP_NAME, "Olá, {$user['name']}.\n\nSeu novo código para entrar no " . APP_NAME . " é:\n\n    {$code}\n\nEle vale por " . TWO_FACTOR_CODE_MINUTES . ' minutos.');
        flash('info', 'Enviamos um novo código.');
        redirect('/login/codigo');
    }

    private function complete(array $user, string $back): never
    {
        Auth::login($user);
        User::touchLogin((int) $user['id']);
        Logger::audit('login', 'users', $user['id'], null, null, (int) $user['id']);
        if ((int) $user['must_change_password'] === 1) {
            redirect('/trocar-senha');
        }
        if ($back !== '' && preg_match('#^/[A-Za-z0-9/_\-]*$#', $back)) {
            redirect($back);
        }
        redirect('/');
    }

    public function logout(): never
    {
        if ($id = Auth::id()) {
            Logger::audit('logout', 'users', $id);
        }
        Auth::logout();
        session_start();
        flash('success', 'Você saiu do sistema.');
        redirect('/login');
    }

    public function changePasswordForm(): never
    {
        $user = Auth::user();
        $forced = (int) $user['must_change_password'] === 1;
        view('auth/change_password', ['title' => $forced ? 'Crie sua senha pessoal' : 'Trocar senha', 'forced' => $forced], $forced ? 'layout_auth' : 'layout');
    }

    public function changePassword(): never
    {
        $user = Auth::user();
        $forced = (int) $user['must_change_password'] === 1;
        $v = new Validator($_POST);
        if (!$forced && !password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
            $v->add('current_password', 'Senha atual incorreta.');
        }
        $new = (string) ($_POST['new_password'] ?? '');
        $v->password('new_password', 'password_confirm', $user['email']);
        if (password_verify($new, $user['password_hash'])) {
            $v->add('new_password', 'A nova senha deve ser diferente da atual.');
        }
        if (strcasecmp($new, User::DEFAULT_PASSWORD) === 0 || stripos($new, 'admoema') !== false) {
            $v->add('new_password', 'Escolha uma senha pessoal, diferente da senha padrão e sem o nome da igreja.');
        }
        if (preg_match('/^(.)\1+$|^(?:0123456789|1234567890|abcdefghij|qwertyuiop)/i', $new)) {
            $v->add('new_password', 'Senha previsível demais.');
        }
        if ($v->fails()) {
            with_errors($v->errors());
            redirect('/trocar-senha');
        }
        $hash = User::hash($new);
        User::setPassword((int) $user['id'], $hash, false);
        LoginCode::forgetDevices((int) $user['id']);
        $user['session_version'] = (int) $user['session_version'] + 1;
        Auth::login($user);
        Logger::audit($forced ? 'senha_primeiro_acesso' : 'senha_alterada', 'users', $user['id']);
        flash('success', $forced ? 'Senha criada. Bem-vindo(a) ao ' . APP_NAME . '!' : 'Senha alterada com sucesso.');
        redirect('/');
    }
}
