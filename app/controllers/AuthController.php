<?php
// app/controllers/AuthController.php — login, saída, troca de senha e termo LGPD
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

        if ($email === '' || $password === '') {
            flash('danger', 'Informe e-mail e senha.');
            redirect('/login');
        }

        if (RateLimit::count('login', $key, LOGIN_WINDOW_MINUTES) >= LOGIN_MAX_ATTEMPTS) {
            Logger::audit('login_bloqueado', 'users', null, null, ['email' => $email]);
            flash('danger', 'Muitas tentativas. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos e tente novamente.');
            redirect('/login');
        }

        $user = User::findByEmail($email);
        $ok = $user && password_verify($password, $user['password_hash']);

        if (!$ok) {
            RateLimit::hit('login', $key);
            Logger::audit('login_falhou', 'users', $user['id'] ?? null, null, ['email' => $email]);
            with_errors([], ['email' => $email]);
            flash('danger', 'E-mail ou senha incorretos.');
            redirect('/login');
        }

        if ($user['status'] === 'pendente') {
            flash('warning', 'Seu cadastro ainda está aguardando aprovação da equipe de mídia.');
            redirect('/login');
        }
        if ($user['status'] !== 'ativo' || empty($user['role'])) {
            Logger::audit('login_negado', 'users', $user['id'], null, ['status' => $user['status']]);
            flash('danger', 'Este acesso está desativado. Fale com a liderança da mídia.');
            redirect('/login');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }

        RateLimit::clear('login', $key);
        Auth::login($user);
        User::touchLogin((int) $user['id']);
        Logger::audit('login', 'users', $user['id'], null, null, (int) $user['id']);

        // Só redireciona de volta para caminhos internos
        if ($back !== '' && preg_match('#^/[A-Za-z0-9/_\-]*$#', $back)) {
            redirect($back);
        }
        redirect('/');
    }

    public function logout(): never
    {
        $id = Auth::id();
        if ($id) {
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
        view('auth/change_password', [
            'title'  => 'Definir nova senha',
            'forced' => (int) $user['must_change_password'] === 1,
        ], (int) $user['must_change_password'] === 1 ? 'layout_auth' : 'layout');
    }

    public function changePassword(): never
    {
        $user = Auth::user();
        $forced = (int) $user['must_change_password'] === 1;
        $v = new Validator($_POST);

        if (!$forced) {
            $current = (string) ($_POST['current_password'] ?? '');
            if (!password_verify($current, $user['password_hash'])) {
                $v->add('current_password', 'Senha atual incorreta.');
            }
        }
        $v->password('new_password', 'password_confirm', $user['email']);
        if (password_verify((string) ($_POST['new_password'] ?? ''), $user['password_hash'])) {
            $v->add('new_password', 'A nova senha deve ser diferente da atual.');
        }
        if ($v->fails()) {
            with_errors($v->errors());
            redirect('/trocar-senha');
        }

        $newUser = $user;
        User::setPassword((int) $user['id'], password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT), false);
        $newUser['session_version'] = (int) $user['session_version'] + 1;
        Auth::login($newUser); // renova a sessão atual com a nova versão
        Logger::audit('senha_alterada', 'users', $user['id']);
        flash('success', 'Senha alterada com sucesso.');
        redirect('/');
    }

    public function termsForm(): never
    {
        view('auth/terms', ['title' => 'Termo de uso e privacidade', 'mustAccept' => true, 'wide' => true], 'layout_auth');
    }

    public function termsPublic(): never
    {
        view('auth/terms', ['title' => 'Termo de uso e privacidade', 'mustAccept' => false, 'wide' => true], Auth::check() ? 'layout' : 'layout_auth');
    }

    public function acceptTerms(): never
    {
        $user = Auth::user();
        if (input('accept') !== '1') {
            flash('warning', 'Para continuar é necessário aceitar o termo.');
            redirect('/termo');
        }
        if (!Consent::hasActive((int) $user['id'], 'cadastro', TERMS_VERSION)) {
            $id = Consent::record((int) $user['id'], 'cadastro', TERMS_VERSION);
            Logger::audit('termo_aceito', 'consents', $id, null, ['versao' => TERMS_VERSION]);
        }
        redirect('/');
    }
}
