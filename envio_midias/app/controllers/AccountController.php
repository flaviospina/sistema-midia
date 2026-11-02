<?php
// app/controllers/AccountController.php — "Meus dados": nome, 2ª etapa do login, aparelhos confiáveis e redes sociais da própria pessoa
declare(strict_types=1);

final class AccountController
{
    public function index(): never
    {
        $me = Auth::user();
        view('account/index', [
            'title'     => 'Meus dados',
            'me'        => $me,
            'twoFactor' => User::pref((int) $me['id'], 'two_factor') === '1',
            'twoFactorMode' => TWO_FACTOR_MODE,
            'twoFactorForced' => in_array(TWO_FACTOR_MODE, ['todos'], true) || (TWO_FACTOR_MODE === 'equipe' && Auth::isTeam()),
            'devices'   => Database::all('SELECT id, label, ip, created_at, last_used_at, expires_at FROM trusted_devices WHERE user_id = :u AND expires_at > NOW() ORDER BY id DESC', ['u' => $me['id']]),
            'accounts'  => SocialAccount::forUser((int) $me['id']),
            'cryptoOk'  => Crypto::ready(),
            'canShare'  => Auth::can('social.share'),
            'discovered' => $_SESSION['social_discovered'] ?? null,
        ]);
        unset($_SESSION['social_discovered']);
    }

    public function update(): never
    {
        $me = Auth::user();
        $v = (new Validator($_POST))->required('name', 'seu nome')->max('name', 150, 'Nome')->required('email', 'o e-mail')->email('email');
        $email = mb_strtolower(input('email'));
        if (!$v->fails() && User::emailExists($email, (int) $me['id'])) {
            $v->add('email', 'Este e-mail já está em uso.');
        }
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/meus-dados');
        }
        User::update((int) $me['id'], input('name'), $email);
        User::setPref((int) $me['id'], 'two_factor', input('two_factor') === '1' ? '1' : null);
        Logger::audit('meus_dados_alterados', 'users', $me['id'], ['name' => $me['name'], 'email' => $me['email']], ['name' => input('name'), 'email' => $email, 'two_factor' => input('two_factor') === '1']);
        Auth::refresh();
        flash('success', 'Dados salvos.');
        redirect('/meus-dados');
    }

    public function forgetDevices(): never
    {
        LoginCode::forgetDevices((int) Auth::id());
        Logger::audit('aparelhos_esquecidos', 'users', Auth::id());
        flash('success', 'Todos os aparelhos confiáveis foram esquecidos; o código será pedido no próximo acesso.');
        redirect('/meus-dados');
    }

    // ---- Redes sociais (Meta) -------------------------------------------

    /** Passo 1: com o token colado pela pessoa, lista Páginas e contas Instagram ligadas. */
    public function socialDiscover(): never
    {
        if (!Crypto::ready()) {
            flash('danger', 'O administrador precisa definir APP_KEY no .env antes de guardar tokens.');
            redirect('/meus-dados');
        }
        $token = trim((string) ($_POST['token'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_\-.]{20,600}$/', $token)) {
            flash('danger', 'Token inválido. Cole o token de acesso gerado no Explorador da Graph API (Meta for Developers).');
            redirect('/meus-dados');
        }
        try {
            if (input('app_id') !== '' && input('app_secret') !== '') {
                $ll = Social::longLived(input('app_id'), input('app_secret'), $token);
                $token = $ll['token'];
                $_SESSION['social_token_expires'] = $ll['expires_in'] > 0 ? date('Y-m-d H:i:s', time() + $ll['expires_in']) : null;
            } else {
                $_SESSION['social_token_expires'] = null;
            }
            $found = Social::discover($token);
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect('/meus-dados');
        }
        if (!$found['pages']) {
            flash('warning', 'Nenhuma Página do Facebook encontrada para este token. A conta precisa administrar uma Página (e, para o Instagram, ter a conta profissional ligada a ela).');
            redirect('/meus-dados');
        }
        $_SESSION['social_token'] = $token; // só até a pessoa escolher a conta (passo 2)
        $_SESSION['social_discovered'] = $found;
        redirect('/meus-dados');
    }

    /** Passo 2: grava a conta escolhida com o token cifrado. */
    public function socialSave(): never
    {
        $found = $_SESSION['social_discovered'] ?? null;
        $token = $_SESSION['social_token'] ?? null;
        unset($_SESSION['social_discovered'], $_SESSION['social_token']);
        $pageId = input('page_id');
        $page = null;
        foreach ($found['pages'] ?? [] as $pg) {
            if ($pg['page_id'] === $pageId) {
                $page = $pg;
            }
        }
        if (!$token || !$page) {
            flash('warning', 'A escolha expirou. Cole o token novamente.');
            redirect('/meus-dados');
        }
        $expires = $_SESSION['social_token_expires'] ?? null;
        $saved = [];
        if (input('use_facebook') === '1' && $page['page_token'] !== '') {
            SocialAccount::save((int) Auth::id(), 'facebook', $page['page_id'], $page['page_name'], $page['page_token'], $expires);
            $saved[] = 'Facebook (' . $page['page_name'] . ')';
        }
        if (input('use_instagram') === '1' && $page['ig_id']) {
            // Publicação no Instagram usa o token da Página (que carrega as permissões do Instagram)
            SocialAccount::save((int) Auth::id(), 'instagram', $page['ig_id'], $page['ig_username'] ? '@' . $page['ig_username'] : null, $page['page_token'] ?: $token, $expires);
            $saved[] = 'Instagram (' . ($page['ig_username'] ? '@' . $page['ig_username'] : $page['ig_id']) . ')';
        }
        if (!$saved) {
            flash('warning', 'Nenhuma conta selecionada.');
            redirect('/meus-dados');
        }
        Logger::audit('rede_social_conectada', 'social_accounts', Auth::id(), null, ['contas' => $saved]);
        flash('success', 'Conectado: ' . implode(' e ', $saved) . '. Agora o botão Compartilhar publica direto.');
        redirect('/meus-dados');
    }

    public function socialRemove(): never
    {
        $n = input('network');
        if (isset(SocialAccount::NETWORKS[$n])) {
            SocialAccount::remove((int) Auth::id(), $n);
            Logger::audit('rede_social_removida', 'social_accounts', Auth::id(), null, ['rede' => $n]);
            flash('success', SocialAccount::NETWORKS[$n] . ' desconectado.');
        }
        redirect('/meus-dados');
    }
}
