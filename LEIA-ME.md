# Central de Mídia ADMoema — Instalação (Fases 1 a 4)

Sistema do Ministério de Multimídia · AD Ministério do Belém · Setor 124 Moema.
Requisitos: PHP 8.2+ (com PDO MySQL, GD, fileinfo, mbstring, zip, exif, curl), MySQL 5.7+/MariaDB 10.2+, Apache com `mod_rewrite`.

## 1. Banco de dados

1. cPanel → **Bancos de dados MySQL** → criar banco (ex.: `usuario_midia`) e usuário; conceder **todos os privilégios**.
2. cPanel → **phpMyAdmin** → selecionar o banco → aba **Importar** → enviar `sql/schema.sql`.
   - Pode ser importado de novo em atualizações futuras: só cria o que não existe.
   - Cria o administrador inicial `admin@admoema.com.br` com senha temporária **`TrocarAgora!2026`** (troca obrigatória no primeiro acesso). Para outro e-mail, edite o `INSERT` final antes de importar ou altere depois em *Pessoas*.
   - Cria as pastas iniciais do repositório: Eventos, Ministérios, Identidade Visual e Artes Finais.
   - Cria o modelo de escala "Culto padrão" (som, projeção, 2 câmeras, transmissão, fotografia).
   - Cria a pasta do sistema "Pedidos de arte" e o checklist de identidade visual padrão (7 itens).

## 2. Arquivos

Escolha uma das duas formas. A **opção A** é a recomendada (pastas internas fora da área pública).

### Opção A — app fora do `public_html` (recomendada)

```
/home/USUARIO/midia_app/          ← app/, storage/, sql/, cron/, .env
/home/USUARIO/public_html/midia/  ← conteúdo da pasta public/ (index.php, enviar.php, paths.php, assets/, .htaccess)
```

1. Compacte o projeto, envie pelo **Gerenciador de Arquivos** e extraia em `/home/USUARIO/midia_app`.
2. Mova o **conteúdo** de `midia_app/public/` para `public_html/midia/`.
3. Edite `public_html/midia/paths.php` e aponte para a pasta interna:
   ```php
   define('APP_ROOT', '/home/USUARIO/midia_app');
   ```
4. Dê permissão de escrita (755 ou 775) à pasta `midia_app/storage` e suas subpastas (`files`, `thumbs`, `display`, `tmp_chunks`, `quarantine`, `photos`, `restrictions`, `logs`).

### Opção B — tudo dentro de `public_html/midia`

1. Extraia o projeto inteiro em `public_html/midia/` (ficam lá `public/`, `app/`, `storage/`, `.htaccess`…).
2. O `.htaccess` da raiz já bloqueia `app/`, `storage/`, `sql/`, `cron/` e `.env` e encaminha tudo para `public/`.
3. Não precisa alterar `paths.php`.

## 3. Configuração

1. Copie `.env.example` para `.env` (na pasta onde está `app/`) e preencha:
   - `BASE_URL=https://admoema.com.br/midia`
   - `DB_NAME`, `DB_USER`, `DB_PASS`
   - `DPO_CONTACT` (e-mail exibido no termo de privacidade)
   - Limites do repositório (já vêm com os valores combinados): `UPLOAD_MAX_MB=2048`, cotas `QUOTA_GB_*`, limites de convidado `GUEST_*`, `STORAGE_ALERT_GB`.
   - Escala: `SCHEDULE_WEEKS_AHEAD` (semanas geradas à frente), `SCHEDULE_OVERLOAD_PER_MONTH` (alerta de sobrecarga), `SCHEDULE_ROTATION_DAYS` (janela do rodízio).
   - E-mail (recuperação de senha): `MAIL_DRIVER=mail` usa o `mail()` do PHP e funciona no HostGator sem mais nada; para maior entregabilidade use `smtp` com a conta de e-mail do cPanel (`SMTP_HOST=mail.seudominio`, porta 465 `ssl` ou 587 `tls`, `SMTP_USER`, `SMTP_PASS`). `MAIL_FROM` deve ser um e-mail do próprio domínio.
   - Artes: `ART_MIN_DAYS` (prazo mínimo; abaixo disso o pedido é "urgente") e `ART_PASTORAL_FORMATS` (formatos que exigem aprovação do pastor; padrão `impresso,telao`).
2. Se a URL não abrir as páginas internas (erro 404 do Apache), descomente `RewriteBase /midia/` em `public/.htaccess`.
3. Confira em cPanel → **Selecionar versão do PHP** que a versão é 8.2+ e que `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `zip`, `exif` e `curl` estão marcados.
   - `UPLOAD_CHUNK_MB` (padrão 5) precisa ser **menor** que `post_max_size` e `upload_max_filesize` do PHP (veja em *Opções do PHP*; se estiverem em 2M, reduza `UPLOAD_CHUNK_MB=1`).
   - Sem `zip`, o download em ZIP fica desativado automaticamente (o resto funciona).
4. hCaptcha na página pública (opcional): crie a conta em hcaptcha.com e preencha `HCAPTCHA_ENABLED=1`, `HCAPTCHA_SITE_KEY`, `HCAPTCHA_SECRET`.

## 4. Primeiro acesso

1. Abra `https://admoema.com.br/midia`.
2. Entre com `admin@admoema.com.br` / `TrocarAgora!2026`.
3. O sistema exigirá **nova senha** e o **aceite do termo**.
4. Em *Pessoas → Nova pessoa* cadastre a equipe. Para cada pessoa, o sistema gera uma senha temporária que é exibida **uma única vez** — envie por WhatsApp/pessoalmente. O admin pode redefinir a senha de qualquer pessoa pelo botão de chave na lista *Pessoas* (com confirmação); quem esqueceu a senha usa *Esqueci minha senha* na tela de login e recebe por e-mail um link de uso único, válido por `PASSWORD_RESET_MINUTES` minutos.
5. Em *Ministérios* cadastre os ministérios e, no cadastro das pessoas, marque quem é líder de cada um.
6. Em *Administração → Cultos fixos* cadastre os cultos semanais (dia, horário, modelo de escala): os eventos das próximas semanas são gerados na hora e o cron mantém a agenda cheia. Em *Administração → Modelos de escala* ajuste as vagas por função.
7. Em *Pessoas → Funções* marque quem é **coordenador** de cada área: coordenadores só escalam as funções que coordenam (se não coordenarem nenhuma, escalam todas).
8. Em *Arquivos* crie as subpastas (ex.: Eventos › 2026 › Congresso). Para a pasta de um ministério, use visibilidade **"Equipe + ministério dono"** e escolha o ministério: os líderes dele passam a ver e enviar arquivos lá.

## 5. Cron (recomendado)

cPanel → **Cron Jobs**, uma vez por dia (ex.: 03:00):

```
/usr/local/bin/php /home/USUARIO/midia_app/cron/limpeza.php
```

Faz: limpeza de tentativas de login, anonimização de cadastros pendentes (90 dias), remoção de uploads não concluídos (24 h), exclusão de rejeitados (7 dias) e da lixeira (30 dias), links vencidos, alerta de espaço, logs antigos, **geração dos cultos fixos** e encerramento dos eventos passados.

## 6. Página pública de envio (QR Code)

URL: `https://admoema.com.br/midia/enviar`. Gere o QR Code para o telão/boletim apontando para essa URL. O visitante informa nome, WhatsApp, ministério, evento e aceita a declaração de uso de imagem; os arquivos caem na **quarentena** e aparecem para coordenadores e administradores em *Quarentena*, onde são aprovados (escolhendo pasta e tags) ou rejeitados.

## 7. Escala: como funciona

- **Eventos** (calendário mensal ou lista) são criados à mão ou gerados pelos cultos fixos. Cada evento tem **vagas por função** (de um modelo ou ajustadas na tela *Montar escala*).
- **Montar escala**: o coordenador escolhe pessoas por função (a lista mostra nível, quantas escalas nos últimos dias, indisponibilidade e conflito) ou clica em **Preencher automaticamente**: rodízio justo entre quem está apto/referência e disponível, priorizando quem serviu menos e há mais tempo, com ao menos um "referência" quando há 2+ vagas. Alertas aparecem para sobrecarga, conflito de horário, indisponibilidade e aprendiz escalado.
- **Minha escala**: cada membro confirma ou recusa (com motivo), registra **indisponibilidades** (data, período ou dia fixo da semana) e pode **pedir troca** com um colega da mesma função — o colega aceita e o coordenador aprova. Link ICS pessoal para assinar no Google Agenda/iPhone.
- **Painel da escala**: vagas abertas nos próximos 21 dias, recusas, sobrecarga e trocas pendentes.

## 8. Pedidos de arte e comunicação

- **Quem pede**: líder de ministério (só do seu ministério), pastor ou equipe de mídia, em *Artes → Novo pedido*: título, ministério, evento vinculado, briefing, textos obrigatórios, formatos (story, feed, telão, impresso) e data de publicação. Menos de `ART_MIN_DAYS` dias = pedido **urgente** (aceito, mas sinalizado). Referências são anexadas na página do pedido.
- **Fluxo**: `recebido → em produção → revisão do solicitante → aprovação da mídia → (aprovação pastoral) → aprovado → publicado`, com *ajustes* a qualquer momento e *cancelado*. O designer assume o pedido (ou o coordenador define), envia versões (cada versão é um arquivo do repositório, na pasta "Pedidos de arte"), o solicitante aprova ou pede ajustes com comentário; a mídia só aprova com o **checklist de identidade visual** completo; formatos em `ART_PASTORAL_FORMATS` passam pelo pastor.
- **Kanban** (*Artes → Kanban*) para a equipe; **Atrasos e prazos** para a coordenação (publicação em até 3 dias sem aprovação, sem designer, ministérios que mais pedem).
- **Calendário de comunicação** (*Comunicação*): ao aprovar uma arte, entra uma publicação por formato (story/feed → Instagram, telão, impresso → boletim) na data pedida. A equipe marca como publicado (com link opcional); quando todas as publicações do pedido estão feitas, o pedido vira *publicado*. Também aceita publicações avulsas (aviso no WhatsApp, vídeo no YouTube…).

## 9. Perfis de acesso

| Perfil | Pode |
|---|---|
| admin | Tudo, inclusive ver originais com EXIF, conteúdo "restrito", escalar qualquer função, aprovar artes (mídia e pastoral) |
| coordenador | Pessoas, funções, pastas, quarentena, restrições, links, armazenamento; eventos, cultos fixos, modelos e escala das funções que coordena; aprovação de artes pela mídia, designer, atrasos, checklist |
| membro_midia | Enviar direto para pastas da equipe, restrições de imagem, links; minha escala, indisponibilidades, trocas; produzir artes (kanban, versões), calendário de comunicação |
| lider_ministerio | Ver e enviar na pasta do seu ministério; ver eventos; abrir e acompanhar pedidos de arte do seu ministério; ver as publicações do seu ministério |
| pastor | Ver eventos e a escala montada; abrir pedidos; aprovação pastoral de artes; calendário de comunicação |
| membro_igreja | Ver eventos; ver o que for "todos os usuários"; envios vão para a quarentena |
| Convidado (sem login) | Só a página `/enviar` |

Visibilidade das pastas: **restrito** (admin) · **equipe de mídia** · **equipe + ministério dono** · **todos os usuários logados**. Subpastas herdam; um arquivo pode sobrescrever a da pasta. Arquivo marcado "contém pessoa com restrição de imagem" fica sempre restrito.

## 10. Onde ficam as coisas

- Arquivos do repositório: `storage/files/AAAA/MM/` (nome aleatório; o nome original fica só no banco). Miniaturas em `storage/thumbs`, versão exibida sem EXIF/GPS em `storage/display`, quarentena em `storage/quarantine`. Nada disso é acessível por link direto: toda entrega passa por `/arquivos/{id}/download` com checagem de permissão e registro em `download_log`.
- Fotos de perfil: `storage/photos/`. Fotos de referência de restrições: `storage/restrictions/`.
- Logs técnicos: `storage/logs/app-AAAA-MM.log` e `php-error.log`.
- Trilha de auditoria: *Administração → Auditoria*. Solicitações LGPD: *Administração → Privacidade*.
- Espaço usado por pasta, tipo e pessoa: *Administração → Armazenamento*.

## 11. Atualização de versão

Substitua os arquivos (menos `.env` e `storage/`) e importe novamente `sql/schema.sql`. Se o termo de privacidade mudar, aumente `TERMS_VERSION` no `.env`: todos serão convidados a aceitar a nova versão no próximo acesso.
