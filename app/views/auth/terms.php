<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Termo de uso e privacidade</h2>
    <div class="text-muted small mb-3">Versão <?= e(TERMS_VERSION) ?> · <?= e(APP_NAME) ?></div>

    <div class="terms-text">
      <h3 class="h6">1. Quem trata os dados</h3>
      <p>Os dados são tratados pelo Ministério de Multimídia da Assembleia de Deus – Ministério do Belém – Setor 124 Moema ("Mídia ADMoema"), responsável por este sistema.<?php if (DPO_CONTACT): ?> Contato para assuntos de privacidade: <strong><?= e(DPO_CONTACT) ?></strong>.<?php endif; ?></p>

      <h3 class="h6">2. Quais dados coletamos e para quê</h3>
      <ul>
        <li><strong>Nome, e-mail e WhatsApp:</strong> identificação, acesso ao sistema e comunicação sobre escalas, pedidos de arte e envios de arquivos.</li>
        <li><strong>Foto de perfil (opcional):</strong> identificação da equipe nas escalas.</li>
        <li><strong>Funções, nível e datas de treinamento:</strong> organização das escalas da equipe de mídia.</li>
        <li><strong>Ministério e liderança:</strong> definir o que cada pessoa pode ver e solicitar.</li>
        <li><strong>Registros de acesso e ações (data, hora, IP):</strong> segurança, auditoria e cumprimento de obrigações legais.</li>
      </ul>

      <h3 class="h6">3. Base legal</h3>
      <p>Consentimento do titular (art. 7º, I, da Lei 13.709/2018 – LGPD) para os dados de cadastro e imagem, e legítimo interesse para os registros de segurança e auditoria.</p>

      <h3 class="h6">4. Compartilhamento</h3>
      <p>Os dados ficam armazenados no servidor da igreja e são vistos apenas pela equipe de mídia e pela liderança, conforme o perfil de acesso. Não vendemos nem cedemos dados a terceiros. Integrações futuras (por exemplo, avisos por WhatsApp) serão informadas e dependerão de novo aceite quando exigirem envio de dados a serviços externos.</p>

      <h3 class="h6">5. Guarda e exclusão</h3>
      <p>Os dados são mantidos enquanto a pessoa fizer parte da equipe ou mantiver cadastro ativo. A pedido do titular, os dados pessoais são excluídos ou anonimizados, preservando-se apenas registros exigidos por lei ou necessários à auditoria, sem identificação pessoal.</p>

      <h3 class="h6">6. Seus direitos</h3>
      <p>Você pode, a qualquer momento, em <em>Meus dados</em>: consultar e corrigir seus dados, baixar uma cópia, revogar consentimentos e solicitar a exclusão da conta. Solicitações são respondidas em até 15 dias.</p>

      <h3 class="h6">7. Segurança</h3>
      <p>Senhas são armazenadas de forma irreversível (hash), o acesso é feito por HTTPS e todas as ações administrativas são registradas.</p>

      <h3 class="h6">8. Uso do sistema</h3>
      <p>O acesso é pessoal e intransferível. Arquivos enviados devem respeitar os direitos de imagem das pessoas retratadas e as orientações da liderança.</p>
    </div>

    <?php if ($mustAccept): ?>
      <form method="post" action="<?= url('/termo') ?>" class="mt-3">
        <?= Csrf::field() ?>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="accept" name="accept" value="1" required>
          <label class="form-check-label" for="accept">Li e aceito o termo de uso e privacidade (versão <?= e(TERMS_VERSION) ?>).</label>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary">Aceitar e continuar</button>
          <button class="btn btn-outline-secondary" form="logoutForm">Sair</button>
        </div>
      </form>
      <form method="post" action="<?= url('/sair') ?>" id="logoutForm"><?= Csrf::field() ?></form>
    <?php elseif (!Auth::check()): ?>
      <a href="<?= url('/login') ?>" class="btn btn-outline-primary mt-3">Voltar</a>
    <?php endif; ?>
  </div>
</div>
