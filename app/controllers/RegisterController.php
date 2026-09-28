<?php
// app/controllers/RegisterController.php — autocadastro de membro da igreja (aguarda aprovação)
declare(strict_types=1);

final class RegisterController
{
    public function form(): never
    {
        view('auth/register', ['title' => 'Criar cadastro'], 'layout_auth');
    }

    public function store(): never
    {
        // Honeypot: campo invisível que só robôs preenchem
        if (input('website') !== '') {
            Logger::info('Honeypot acionado no cadastro');
            flash('success', 'Cadastro enviado! Aguarde a aprovação da equipe de mídia.');
            redirect('/login');
        }

        if (RateLimit::count('signup', client_ip(), 60) >= SIGNUP_MAX_PER_HOUR) {
            flash('danger', 'Muitos cadastros a partir desta conexão. Tente novamente mais tarde.');
            redirect('/cadastro');
        }

        $v = (new Validator($_POST))
            ->required('name', 'seu nome completo')->max('name', 150, 'Nome')
            ->required('email', 'seu e-mail')->email('email')
            ->required('whatsapp', 'seu WhatsApp')->whatsapp('whatsapp')
            ->password('password', 'password_confirm', input('email'));

        if (input('accept') !== '1') {
            $v->add('accept', 'É necessário aceitar o termo de uso e privacidade.');
        }
        if (!$v->fails() && User::emailExists(input('email'))) {
            $v->add('email', 'Já existe um cadastro com este e-mail.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), ['name' => input('name'), 'email' => input('email'), 'whatsapp' => input('whatsapp')]);
            redirect('/cadastro');
        }

        RateLimit::hit('signup', client_ip());
        $data = [
            'name'     => input('name'),
            'email'    => input('email'),
            'whatsapp' => Validator::normalizePhone(input('whatsapp')),
        ];
        $id = User::create($data, password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'membro_igreja', false, 'pendente');
        Consent::record($id, 'cadastro', TERMS_VERSION);
        Logger::audit('autocadastro', 'users', $id, null, $data, $id);

        flash('success', 'Cadastro enviado! Você poderá entrar assim que a equipe de mídia aprovar.');
        redirect('/login');
    }
}
