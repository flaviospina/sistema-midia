# Envio de Mídias ADMoema — instalação e uso

Sistema de envio, organização e visualização das fotos dos cultos e eventos da AD Moema, com área de login protegida, melhoria de fotos no navegador, links de compartilhamento com pré-visualização e publicação nas redes sociais pela conta de cada pessoa. É um sistema separado da Central de Mídia, mas preparado para o login único: usa as mesmas tabelas de usuários quando instalado no mesmo banco.

Arquitetura: **PHP 8.2 puro, PDO com prepared statements, MVC** (`app/core` = núcleo, `app/controllers`, `app/models`, `app/services`, `app/views`) e **URLs amigáveis** pelo `.htaccess` (toda requisição passa pelo `index.php`).

Publicação: `https://admoema.com.br/proj/envio_midias`.

## 1. Banco de dados

1. cPanel → **Bancos de dados MySQL**: use o banco da Central de Mídia (recomendado: login único) ou crie um banco novo e um usuário com todos os privilégios.
2. phpMyAdmin → selecione o banco → **Importar** → `sql/schema.sql`. O arquivo é idempotente (pode importar de novo nas atualizações). No banco compartilhado ele só cria as tabelas novas (`photo_*`, `social_*`, `share_links`, `login_codes`, `trusted_devices`).

## 2. Arquivos

Tudo fica em **uma única pasta**, exatamente como está no repositório:

```
public_html/proj/envio_midias/
├── index.php          ← front controller (URLs amigáveis)
├── .htaccess          ← reescrita + bloqueio de app/, storage/, sql/, cron/ e .env
├── assets/            ← css, js, img (únicos arquivos servidos diretamente)
├── app/               ← core/, controllers/, models/, services/, views/, routes.php
├── storage/           ← albums/, thumbs/, tmp/, logs/ (negado pelo .htaccess)
├── sql/schema.sql
├── cron/limpeza.php
├── .env.example  →  copie para .env
└── LEIA-ME.md
```

1. Envie o conteúdo da pasta `envio_midias/` para `public_html/proj/envio_midias/` (mantendo a estrutura acima). Nada de `public/` nem `paths.php`: o `index.php` já sabe onde está.
2. Dê permissão de escrita (755 ou 775) a `storage/` e às subpastas `albums`, `thumbs`, `tmp` e `logs`.
3. Confirme que o `.htaccess` foi enviado (arquivos que começam com ponto ficam ocultos no gerenciador de arquivos: ative *Mostrar arquivos ocultos* nas configurações).
4. Se as URLs amigáveis derem 404 nesta hospedagem, abra o `.htaccess` e descomente `RewriteBase /proj/envio_midias/`.

Opcional e mais seguro: guardar as fotos fora da pasta pública apontando `STORAGE_PATH` no `.env` para uma pasta acima de `public_html` (ex.: `/home/USUARIO/envio_midias_storage`), com as mesmas subpastas.

## 3. Configuração

Copie `.env.example` para `.env` e preencha:

- `BASE_URL=https://admoema.com.br/proj/envio_midias` (sem barra no final). Se ficar vazio, o sistema deduz o caminho pela pasta do `index.php`.
- `APP_KEY`: 64 caracteres hexadecimais (32 bytes). Gere em random.org (bytes, hex) ou com `php -r "echo bin2hex(random_bytes(32));"`. Sem ela, as contas de redes sociais não podem ser guardadas.
- `DB_*`: dados do banco.
- `SESSION_NAME=ADMOEMA_SID` e `SESSION_COOKIE_PATH=/`: iguais aos da Central de Mídia para o login valer nos dois.
- `TWO_FACTOR_MODE`: `opcional` (cada pessoa liga em *Meus dados*), `equipe` (obrigatório para admin, fotógrafos e redes sociais), `todos` ou `desligado`.
- `MAIL_*`: necessário para o código de acesso e o "Esqueci minha senha". `mail` usa o PHP do HostGator; `smtp` usa uma conta de e-mail do cPanel (porta 465/SSL).
- `UPLOAD_MAX_MB` (padrão 80 por foto) e `UPLOAD_CHUNK_MB` (padrão 2): o envio é em pedaços, então não depende de `upload_max_filesize`.

## 4. Primeiro acesso

Entre em `admoema.com.br/proj/envio_midias` com `admin@admoema.com.br` e a senha padrão **`ADMoema2026@1`**. O sistema exige a criação de uma senha pessoal antes de liberar qualquer tela. Depois, em *Administração → Usuários*, cadastre as pessoas: todas entram com a mesma senha padrão `ADMoema2026@1` e são obrigadas a trocá-la no primeiro acesso (a tela mostra o aviso). Quem já tem cadastro na Central de Mídia (mesmo banco) aparece em *Liberar quem já tem cadastro* e entra com a senha que já usa.

Perfis:

| Perfil | Pode |
|---|---|
| Administrador | tudo: usuários, auditoria, pastas, fotos, links, publicação |
| Fotógrafo(a) / equipe de mídia | criar pastas, enviar, editar, excluir fotos, salvar versões melhoradas, gerar links públicos |
| Redes sociais | ver tudo (inclusive pastas ocultas), enviar, melhorar, salvar versões, gerar links e **publicar** nas redes com a própria conta |
| Membro da igreja | ver e baixar fotos das pastas visíveis, melhorar fotos e baixar o resultado |

## 5. Segurança do login

- Senhas com **Argon2id** (ou bcrypt custo 12 se o servidor não tiver Argon2); mínimo 10 caracteres com letras e números; recusa a senha padrão, o nome da igreja e sequências óbvias.
- **Bloqueio** após 5 tentativas erradas por e-mail+IP em 15 min e 30 tentativas por IP por hora; resposta em tempo constante (não revela se o e-mail existe); campo-armadilha contra robôs.
- **Verificação em duas etapas** por código de 6 dígitos no e-mail (10 min, 5 tentativas), com opção de "confiar neste aparelho" por 30 dias.
- Sessão com cookie `HttpOnly`, `Secure`, `SameSite=Lax`, ID longo, renovação periódica, expiração por inatividade (60 min) e absoluta (12 h), presa ao navegador que a criou; troca de senha ou desativação derruba as outras sessões.
- CSRF em todo POST, cabeçalhos CSP/HSTS/X-Frame-Options, arquivos entregues só pelo PHP após checar permissão, `app/` e `storage/` negados pelo `.htaccess`, nomes de arquivo saneados, MIME validado pelo conteúdo, EXIF/GPS removidos da versão exibida.
- Trilha de auditoria (*Administração → Auditoria*): logins, bloqueios, códigos, redefinições, pastas, envios, exclusões, links, publicações.

## 6. Pastas dos cultos

Ao criar uma pasta, escolha a data: o sistema sugere o nome pelo dia da semana (domingo *Culto com a Família*, terça *Culto de Ensino*, quarta *Círculo de Oração*, quinta *Culto Público*, sábado *Culto dos Jovens e Adolescentes*; segunda e sexta pedem o nome). Você pode manter ou digitar outro nome e precisa **confirmar** antes de criar. A pasta física fica em `storage/albums/AAAA-MM-DD Nome do evento/` (sem acentos) e as fotos são gravadas lá com o nome original saneado. Miniaturas e versões de exibição ficam em `storage/thumbs/`. Uma pasta pode ser *oculta* (só a equipe vê).

## 7. Melhorar fotos

Na foto ampliada → **Melhorar**. Tudo roda no navegador (o original nunca é alterado): *Melhorar automaticamente* (níveis pela análise da foto + cor + nitidez), estilos (Natural, Vívido, Culto, Frio, Dramático, Retrato suave, P&B, Sépia, Cinema) e ajustes finos (brilho, contraste, exposição, realces, sombras, saturação, vivacidade, temperatura, matiz, clareza, nitidez, ruído, vinheta, grão, girar). Segure *Original* para comparar. **Baixar** gera um JPEG em resolução completa; a equipe pode **Salvar no álbum** como versão editada ao lado do original.

## 8. Redes sociais

Há três caminhos:

- **Link público com pré-visualização**: no visualizador → *Link* (ou no álbum → *Link público*, escolhendo a validade). Abre sem login e traz as metatags Open Graph/Twitter com a própria imagem, então WhatsApp, Facebook, Instagram e Telegram mostram a foto na prévia. Pode ser desativado a qualquer momento; os acessos são contados.
- **Pelo celular, sem configurar nada**: na foto ampliada → *Compartilhar*. O aparelho abre Instagram, WhatsApp, Facebook etc. com a imagem anexada.
- **Publicação direta pelo sistema** (perfil *Redes sociais* ou admin): cada pessoa conecta a **própria** conta em *Meus dados → Redes sociais*; o administrador não vê nem precisa das credenciais. Requisitos da Meta:
  1. Conta do Instagram **profissional** (Comercial ou Criador) ligada a uma **Página do Facebook** administrada pela pessoa.
  2. Um app em [developers.facebook.com](https://developers.facebook.com) (tipo Empresa) com os produtos *Instagram Graph API* e *Facebook Login*.
  3. No *Explorador da Graph API*, gerar um **token de acesso do usuário** com as permissões `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, `instagram_content_publish`, `business_management`.
  4. Colar o token em *Meus dados*. Informando também o *App ID* e o *App Secret*, o sistema troca por um token de longa duração (≈ 60 dias), lista as Páginas e contas Instagram encontradas e guarda o token cifrado (AES-256-GCM com a `APP_KEY`).

  Ao publicar, o sistema gera um link temporário da foto (30 min) que a Meta baixa, envia a legenda e registra o resultado no histórico. Limites da Meta: JPEG até 8 MB, proporção entre 4:5 e 1.91:1, 25 publicações por dia por conta.

## 9. Cron (recomendado)

cPanel → Cron Jobs → a cada hora:

```
php /home/USUARIO/public_html/proj/envio_midias/cron/limpeza.php
```

Remove envios abandonados, códigos e links expirados.

## 10. Atualização

Substitua os arquivos (menos `.env` e `storage/`) e importe `sql/schema.sql` de novo. CSS e JS são versionados pela data do arquivo, então o navegador baixa a versão nova automaticamente.
