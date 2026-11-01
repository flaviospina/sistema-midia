<?php
// app/controllers/PasswordResetController.php — "Esqueci minha senha" por e-mail com link de uso único
declare(strict_types=1);

final class PasswordResetController
{
    private const NEUTRAL = 'Se este e-mail estiver cadastrado, você receberá em instantes um link para redefinir a senha. Confira também a caixa de spam.';

    public function form(): never
    {
        view('auth/forgot', ['title' => 'Esqueci minha senha'], 'layout_auth');
    }

    public function request(): never
    {
        $email = mb_strtolower(input('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            with_errors(['email' => 'Informe um e-mail válido.'], ['email' => $email]);
            redirect('/esqueci-senha');
        }
        if (RateLimit::count('reset', client_ip(), 60) >= 10) {
            Logger::audit('senha_recuperacao_bloqueada', 'users', null, null, ['email' => $email]);
            flash('info', self::NEUTRAL);
            redirect('/login');
        }
        RateLimit::hit('reset', client_ip());
        $user = User::findByEmail($email);
        if ($user && $user['status'] === 'ativo' && !empty($user['role']) && PasswordReset::recentCount((int) $user['id']) < 3) {
            $token = PasswordReset::issue((int) $user['id']);
            $body = "Olá, {$user['name']}.\n\nRecebemos um pedido para redefinir a senha do seu acesso ao " . APP_NAME . ".\n\nAbra o link abaixo (válido por " . PASSWORD_RESET_MINUTES . " minutos e de uso único):\n\n" . absolute_url('/redefinir-senha/' . $token) . "\n\nSe você não pediu isso, ignore esta mensagem: sua senha continua a mesma.\n\nPedido feito do IP " . client_ip() . ' em ' . date('d/m/Y H:i') . '.';
            $sent = Mailer::send($user['email'], 'Redefinição de senha — ' . APP_NAME, $body);
            Logger::audit('senha_recuperacao_solicitada', 'users', $user['id'], null, ['enviado' => $sent], (int) $user['id']);
        } else {
            Logger::audit('senha_recuperacao_solicitada', 'users', $user['id'] ?? null, null, ['email' => $email, 'enviado' => false]);
        }
        flash('info', self::NEUTRAL);
        redirect('/login');
    }

    public function resetForm(string $token): never
    {
        $r = PasswordReset::findValid($token);
        if (!$r || $r['status'] !== 'ativo') {
            flash('danger', 'Este link é inválido, já foi usado ou expirou. Peça um novo.');
            redirect('/esqueci-senha');
        }
        view('auth/reset', ['title' => 'Nova senha', 'token' => $token, 'name' => $r['name']], 'layout_auth');
    }

    public function reset(string $token): never
    {
        $r = PasswordReset::findValid($token);
        if (!$r || $r['status'] !== 'ativo') {
            flash('danger', 'Este link é inválido, já foi usado ou expirou. Peça um novo.');
            redirect('/esqueci-senha');
        }
        $v = (new Validator($_POST))->password('new_password', 'password_confirm', $r['email']);
        if (strcasecmp((string) ($_POST['new_password'] ?? ''), User::DEFAULT_PASSWORD) === 0) {
            $v->add('new_password', 'Escolha uma senha pessoal, diferente da senha padrão.');
        }
        if ($v->fails()) {
            with_errors($v->errors());
            redirect('/redefinir-senha/' . $token);
        }
        Database::transaction(static function () use ($r): void {
            User::setPassword((int) $r['user_id'], User::hash((string) $_POST['new_password']), false);
            PasswordReset::consume((int) $r['id']);
            LoginCode::forgetDevices((int) $r['user_id']);
            RateLimit::clear('login', $r['email'] . '|' . client_ip());
        });
        Logger::audit('senha_redefinida_por_link', 'users', $r['user_id'], null, ['reset_id' => $r['id']], (int) $r['user_id']);
        flash('success', 'Senha redefinida. Entre com a nova senha.');
        redirect('/login');
    }
}
