# CLAUDE.md — Central de Mídia ADMoema

Sistema do Ministério de Multimídia da Assembleia de Deus – Ministério do Belém – Setor 124 Moema.
Responsável: Flávio Roberto Spina (IT Thrive). Nome de trabalho: **Central de Mídia ADMoema**.
Publicação prevista: `admoema.com.br/midia` (a confirmar), ao lado de `/pix` e `/biblio`.

---

## 1. Regras de trabalho (obrigatórias)

1. **Trabalhe em fases.** Execute UMA fase por vez (seção 6). Ao terminar, apresente o resumo, os passos de publicação e pergunte: "Posso seguir para a fase N?". Nunca avance sem o "ok".
2. **Produção, não protótipo.** Validação, tratamento de erro, mensagens amigáveis em pt-BR, logs e segurança já na primeira entrega.
3. **Publicação sem terminal.** O deploy é feito pelo cPanel (gerenciador de arquivos + phpMyAdmin). Nada de Composer, npm ou SSH em produção. Bibliotecas de front via CDN.
4. **Idioma.** UI, comentários e documentação em pt-BR. Identificadores em inglês: snake_case no PHP/MySQL, camelCase no JS.
5. **Arquivos completos.** Ao alterar um arquivo, entregue-o inteiro.
6. **Explique só decisões não óbvias.** O responsável tem mais de 40 anos de TI.

## 2. Stack e restrições do ambiente

| Camada | Padrão |
|---|---|
| Backend | PHP 8.2 puro, sem framework, PDO + prepared statements |
| Banco | MySQL/MariaDB, utf8mb4. **Compatível com MySQL 5.7**: sem CTE, sem window functions, sem CHECK |
| Front | HTML + CSS + JS vanilla, mobile-first, Bootstrap 5 via CDN |
| Gráficos | Chart.js via CDN |
| Automação | n8n (VPS separada) via webhooks — Fase 5 |
| WhatsApp | Evolution API via n8n — Fase 5 |
| Hospedagem | HostGator compartilhado + cPanel. Sem Node em produção. Cron do cPanel (mínimo 5 min) |

Cuidados já conhecidos neste servidor:
- `allow_url_fopen` pode estar desativado: **use cURL** para qualquer chamada HTTP, nunca `file_get_contents()` com URL.
- Não depender de `ffmpeg`, `imagick` ou binários externos. Usar GD para imagens.
- Não presumir valores de `upload_max_filesize` / `post_max_size`: o upload é feito em **chunks** (seção 5.2).

## 3. Estrutura do projeto

```
midia/
├── public/                 ← raiz publicada (admoema.com.br/midia)
│   ├── index.php           ← front controller / roteador
│   ├── enviar.php          ← página pública de envio de arquivos (convidado)
│   ├── assets/ (css, js, img)
│   └── .htaccess
├── app/
│   ├── config.php  Database.php  Auth.php  Csrf.php  Logger.php
│   ├── storage/ StorageDriver.php  LocalDriver.php  (GoogleDriveDriver.php na Fase 5)
│   ├── controllers/  models/  views/
├── storage/                ← FORA da raiz pública (ou protegido com Deny from all)
│   ├── files/  thumbs/  tmp_chunks/  quarantine/
├── cron/                   ← scripts chamados pelo cron do cPanel
├── sql/schema.sql          ← idempotente (CREATE TABLE IF NOT EXISTS)
├── .env.example
└── LEIA-ME.md              ← instalação passo a passo via cPanel
```

## 4. Perfis de acesso

| Perfil | Pode |
|---|---|
| `admin` (líder da mídia) | Tudo, inclusive configurações e perfis |
| `coordenador` (coord. de área: som, foto, design…) | Montar escalas da sua área, moderar repositório, produzir artes |
| `membro_midia` | Ver/confirmar sua escala, informar indisponibilidade, usar o repositório, produzir artes |
| `lider_ministerio` (líder de outro ministério) | Abrir e acompanhar pedidos de arte, acessar pasta do seu ministério |
| `pastor` | Aprovação final de artes (quando exigida), painel de leitura |
| `membro_igreja` (cadastro simples, aprovado pela mídia) | Enviar arquivos e ver o que for marcado como visível para membros |
| Convidado (sem login) | Somente a página `/enviar`, com envio para quarentena |

Permissões checadas no servidor em toda rota (nunca só esconder botão).

## 5. Módulos

### 5.1 Membros da mídia
Cadastro completo, foto, WhatsApp, funções (som, projeção, câmera, transmissão, fotografia, design, redes sociais, edição de vídeo), nível por função (aprendiz / apto / referência), status (ativo, afastado, em treinamento), data de entrada, termo LGPD aceito (data, hora, IP).

### 5.2 Repositório de arquivos (qualquer membro envia)
Objetivo: um único lugar para fotos, vídeos, áudios, artes e documentos da igreja, substituindo arquivos espalhados no WhatsApp.

**Entrada de arquivos**
- Usuário logado: envia direto para uma pasta onde tenha permissão.
- Convidado / membro sem login: página pública `/enviar` (divulgada por QR Code no telão e no boletim). Campos: nome, WhatsApp, ministério, evento (lista dos eventos recentes + "outro"), descrição e aceite obrigatório de declaração de uso de imagem. Tudo cai na **quarentena**.
- Proteção da página pública: honeypot, limite por IP (arquivos/hora e MB/dia configuráveis no `.env`), hCaptcha opcional por flag.

**Upload**
- Uploader JS vanilla próprio: múltiplos arquivos, arrastar-e-soltar, galeria/câmera no celular, barra de progresso por arquivo.
- **Chunks de 5 MB** (configurável), com retentativa automática e **retomada** após queda de conexão (consulta os chunks já recebidos).
- Servidor monta o arquivo em `storage/tmp_chunks`, valida e move para o destino. Sessões de upload expiram em 24 h (limpeza por cron).
- Tamanho máximo por arquivo e cota por usuário configuráveis por perfil no `.env`.

**Validação e segurança**
- MIME validado com `finfo` + lista de extensões permitidas: jpg, jpeg, png, webp, gif, heic, mp4, mov, m4v, mp3, m4a, wav, pdf, docx, pptx, xlsx, psd, ai, zip. SVG e HTML: não permitidos.
- Nome gravado aleatório; nome original só no banco.
- SHA-256 de cada arquivo para **detectar duplicados** (avisar antes de gravar a cópia).
- Imagens: gerar miniatura com GD, corrigir orientação e **remover EXIF/GPS** da versão exibida (original preservado apenas para admin).
- Vídeos: miniatura capturada **no navegador** (elemento `<video>` + `<canvas>`) no momento do upload e enviada junto; duração lida no front.
- HEIC: armazenar normalmente; miniatura genérica quando GD não suportar.

**Organização**
- Pastas hierárquicas (ex.: Ano › Evento; Ministérios › Jovens; Identidade Visual; Artes Finais).
- Vínculo opcional do arquivo com **evento** e com **pedido de arte**.
- Tags livres + categorias fixas (foto, vídeo, áudio, arte final, documento, identidade visual).
- Busca por nome, tag, evento, ministério, período, tipo e quem enviou.
- Visualização em grade (miniaturas) e lista; pré-visualização de imagem, vídeo (player com HTTP Range) e PDF.

**Visibilidade (por pasta, herdável, sobrescrevível por arquivo)**
`restrito` (admin) · `midia` (equipe) · `ministerio` (equipe + ministério dono) · `membros` (todos logados).

**Moderação**
- Fila de quarentena para coordenadores/admin: aprovar (escolhe pasta e tags), rejeitar (motivo) ou marcar como "restrição de imagem".
- Rejeitados apagados após 7 dias; lixeira geral com restauração por 30 dias (cron).

**Entrega de arquivos**
- Todo download passa por `download.php` com checagem de permissão; nunca link direto para `storage/`.
- Suporte a HTTP Range (vídeo), `Content-Disposition`, `X-Content-Type-Options: nosniff`.
- Download de pasta/seleção em ZIP (`ZipArchive`; se indisponível, desabilitar a função com aviso).
- Links de compartilhamento com token, validade e limite de downloads (para enviar a quem não tem login).
- Registro de downloads (quem, quando, qual arquivo).

**Armazenamento**
- Camada `StorageDriver` com `LocalDriver` já na Fase 2. `GoogleDriveDriver` fica preparado para a Fase 5 (decisão pendente, seção 8).
- Painel mostra espaço usado por pasta/tipo e alerta quando passar do limite definido no `.env`.

### 5.3 Direito de imagem
Cadastro de pessoas que **não autorizam** aparecer em fotos/transmissão, com foto de referência visível apenas à equipe de mídia; marcação especial para menores (nome do responsável). Arquivos podem ser marcados "contém pessoa com restrição" e ficam `restrito`.

### 5.4 Eventos e escala
- Cultos fixos por recorrência (gerados automaticamente) e eventos especiais.
- Vagas por função em cada evento; modelos de escala reutilizáveis.
- Indisponibilidades do membro (data única ou recorrente).
- Sugestão automática com rodízio justo (última escala, nível, indisponibilidade); alerta de conflito e de sobrecarga.
- Confirmação/recusa pelo membro; troca entre membros com aprovação do coordenador.
- Calendário mensal + visão "minha escala"; exportação ICS.

### 5.5 Pedidos e aprovação de artes
- Líder de ministério abre pedido: título, evento vinculado, briefing, textos, formatos (story, feed, telão, impresso), anexos, data de publicação. Prazo mínimo configurável (padrão 10 dias) com aviso quando for menor.
- Fluxo: `recebido → em_producao → revisao_solicitante → aprovacao_midia → aprovacao_pastoral (opcional por tipo) → aprovado → publicado` (+ `ajustes` e `cancelado`).
- Versões da arte (cada versão é um arquivo do repositório), comentários por versão, histórico completo.
- Checklist de identidade visual antes da aprovação da mídia.
- Kanban para a equipe de design; painel de atrasos.

### 5.6 Calendário de comunicação
Artes aprovadas entram numa agenda de postagens (redes sociais, telão, boletim) ligada ao evento.

### 5.7 Patrimônio, checklist e ocorrências (Fase 6)
Inventário com QR Code, empréstimo/devolução, manutenção; checklist pré-culto por função; relatório pós-culto (ocorrências, audiência da live).

### 5.8 Capacitação (Fase 6)
Trilha por função; membro só é sugerido na escala de uma função se estiver `apto` nela.

### 5.9 Painel do líder
Frequência, recusas, sobrecarga, pedidos atrasados, ministérios que mais demandam, uso do repositório.

## 6. Fases de entrega

| Fase | Conteúdo |
|---|---|
| 1 | Base: schema, config, login, perfis, auditoria, LGPD, cadastro de membros e ministérios, funções |
| 2 | Repositório completo (5.2) + direito de imagem (5.3) + página pública `/enviar` |
| 3 | Eventos, indisponibilidades e escala (5.4) |
| 4 | Pedidos de arte e calendário de comunicação (5.5, 5.6) |
| 5 | Integrações n8n: WhatsApp (escala, lembretes, novos envios na quarentena, status de artes) e, se aprovado, migração de arquivos pesados para o Google Drive |
| 6 | Patrimônio, checklist, ocorrências, capacitação e painel do líder completo |

## 7. Modelo de dados (base — detalhar em cada fase)

- `users`, `ministries`, `media_functions`, `member_functions` (nível, data de treinamento)
- `consents` (tipo: cadastro, uso_imagem, envio_arquivo; data, IP, user agent, revogação)
- `image_restrictions`
- `audit_log` (usuário, ação, entidade, id, dados antes/depois, IP, data)
- Repositório: `folders`, `files` (driver, referência, mime, tamanho, sha256, dimensões, duração, miniatura, visibilidade, status: quarentena/aprovado/rejeitado/lixeira), `file_tags`, `tags`, `upload_sessions`, `guest_uploads`, `share_links`, `download_log`
- Escala: `events`, `event_recurrences`, `event_slots`, `assignments`, `unavailability`, `schedule_templates`
- Artes: `art_requests`, `art_request_versions`, `art_request_comments`, `approvals`
- Fase 6: `equipment`, `equipment_loans`, `checklists`, `incidents`, `trainings`

## 8. Decisões pendentes (perguntar ao Flávio antes da fase correspondente)

1. URL final (`admoema.com.br/midia`?) e se o login será compartilhado com `/pix` e `/biblio` no futuro.
2. Arquivos pesados (vídeos, acervo de anos): ficar só no HostGator ou migrar os aprovados para um Google Drive da igreja via n8n? (Envolve imagens de membros em serviço externo: exige aprovação explícita.)
3. Limites: tamanho máximo por arquivo e cota por perfil.
4. Quais tipos de arte exigem aprovação pastoral.
5. Se `membro_igreja` terá login ou apenas a página pública `/enviar`.

## 9. Checklist de segurança (toda fase)
PDO com prepared statements · `htmlspecialchars()` em toda saída · `password_hash()` · `session_regenerate_id(true)` no login · CSRF em todo POST · `.env`, `app/` e `storage/` inacessíveis pela web · uploads renomeados e validados · logs de ação · exclusão/anonimização de dados pessoais a pedido do titular.
