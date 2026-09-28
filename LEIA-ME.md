# Central de Mídia ADMoema — Instalação (Fase 1)

Sistema do Ministério de Multimídia · AD Ministério do Belém · Setor 124 Moema.
Requisitos: PHP 8.2+ (com PDO MySQL, GD, fileinfo, mbstring), MySQL 5.7+/MariaDB 10.2+, Apache com `mod_rewrite`.

## 1. Banco de dados

1. cPanel → **Bancos de dados MySQL** → criar banco (ex.: `usuario_midia`) e usuário; conceder **todos os privilégios**.
2. cPanel → **phpMyAdmin** → selecionar o banco → aba **Importar** → enviar `sql/schema.sql`.
   - Pode ser importado de novo em atualizações futuras: só cria o que não existe.
   - Cria o administrador inicial `admin@admoema.com.br` com senha temporária **`TrocarAgora!2026`** (troca obrigatória no primeiro acesso). Para outro e-mail, edite o `INSERT` final antes de importar ou altere depois em *Pessoas*.

## 2. Arquivos

Escolha uma das duas formas. A **opção A** é a recomendada (pastas internas fora da área pública).

### Opção A — app fora do `public_html` (recomendada)

```
/home/USUARIO/midia_app/          ← app/, storage/, sql/, cron/, .env
/home/USUARIO/public_html/midia/  ← conteúdo da pasta public/ (index.php, paths.php, assets/, .htaccess)
```

1. Compacte o projeto, envie pelo **Gerenciador de Arquivos** e extraia em `/home/USUARIO/midia_app`.
2. Mova o **conteúdo** de `midia_app/public/` para `public_html/midia/`.
3. Edite `public_html/midia/paths.php` e aponte para a pasta interna:
   ```php
   define('APP_ROOT', '/home/USUARIO/midia_app');
   ```
4. Dê permissão de escrita (755 ou 775) à pasta `midia_app/storage` e suas subpastas.

### Opção B — tudo dentro de `public_html/midia`

1. Extraia o projeto inteiro em `public_html/midia/` (ficam lá `public/`, `app/`, `storage/`, `.htaccess`…).
2. O `.htaccess` da raiz já bloqueia `app/`, `storage/`, `sql/`, `cron/` e `.env` e encaminha tudo para `public/`.
3. Não precisa alterar `paths.php`.

## 3. Configuração

1. Copie `.env.example` para `.env` (na pasta onde está `app/`) e preencha:
   - `BASE_URL=https://admoema.com.br/midia`
   - `DB_NAME`, `DB_USER`, `DB_PASS`
   - `DPO_CONTACT` (e-mail exibido no termo de privacidade)
2. Se a URL não abrir as páginas internas (erro 404 do Apache), descomente `RewriteBase /midia/` em `public/.htaccess`.
3. Confira em cPanel → **Selecionar versão do PHP** que a versão é 8.2+ e que `pdo_mysql`, `gd`, `fileinfo`, `mbstring` estão marcados.

## 4. Primeiro acesso

1. Abra `https://admoema.com.br/midia`.
2. Entre com `admin@admoema.com.br` / `TrocarAgora!2026`.
3. O sistema exigirá **nova senha** e o **aceite do termo**.
4. Em *Pessoas → Nova pessoa* cadastre a equipe. Para cada pessoa, o sistema gera uma senha temporária que é exibida **uma única vez** — envie por WhatsApp/pessoalmente.
5. Em *Administração → Funções da equipe* ajuste as funções (som, projeção, câmera…), se necessário.
6. Em *Ministérios* cadastre os ministérios e, no cadastro das pessoas, marque quem é líder de cada um.

## 5. Cron (opcional, recomendado)

cPanel → **Cron Jobs**, uma vez por dia (ex.: 03:00):

```
/usr/local/bin/php /home/USUARIO/midia_app/cron/limpeza.php
```

Remove registros de controle de tentativas, anonimiza cadastros pendentes com mais de 90 dias e apaga logs antigos.

## 6. Perfis de acesso

| Perfil | Pode |
|---|---|
| admin | Tudo |
| coordenador | Ver pessoas, aprovar cadastros de membros, definir funções da equipe |
| membro_midia | Ver ministérios, próprios dados |
| lider_ministerio / pastor / membro_igreja | Próprios dados (módulos específicos chegam nas próximas fases) |

Membros da igreja podem se cadastrar sozinhos em `/cadastro`; o acesso só é liberado após aprovação em *Cadastros pendentes*.

## 7. Onde ficam as coisas

- Logs técnicos: `storage/logs/app-AAAA-MM.log` e `php-error.log`.
- Fotos de perfil: `storage/photos/` (nunca acessíveis por link direto; servidas via `/usuarios/{id}/foto` com verificação de permissão).
- Trilha de auditoria: menu *Administração → Auditoria*.
- Solicitações LGPD (acesso, correção, exclusão): *Administração → Privacidade*. Prazo interno sugerido: 15 dias.

## 8. Atualização de versão

Substitua os arquivos (menos `.env` e `storage/`) e importe novamente `sql/schema.sql`. Se o termo de privacidade mudar, aumente `TERMS_VERSION` no `.env`: todos serão convidados a aceitar a nova versão no próximo acesso.
