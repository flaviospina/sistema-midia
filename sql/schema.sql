-- =====================================================================
-- Central de Mídia ADMoema — schema do banco (Fase 1)
-- Idempotente: pode ser importado mais de uma vez no phpMyAdmin.
-- Compatível com MySQL 5.7+ e MariaDB 10.2+ (sem CTE, window functions ou CHECK).
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ---------------------------------------------------------------------
-- Identidade (tabela pensada para ser compartilhada com /pix e /biblio)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(150) NOT NULL,
    email                VARCHAR(191) NOT NULL,
    whatsapp             VARCHAR(20)  NULL,
    photo_path           VARCHAR(120) NULL,
    password_hash        VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    status               ENUM('pendente','ativo','inativo','anonimizado') NOT NULL DEFAULT 'ativo',
    session_version      INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at        DATETIME     NULL,
    anonymized_at        DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Perfil do usuário em cada sistema (app_code = 'midia', futuramente 'pix', 'biblio')
CREATE TABLE IF NOT EXISTS user_roles (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    app_code   VARCHAR(20)  NOT NULL,
    role       VARCHAR(30)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_roles (user_id, app_code),
    KEY idx_user_roles_role (app_code, role),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Ministérios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ministries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ministries_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ministry_users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ministry_id INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    is_leader   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ministry_users (ministry_id, user_id),
    KEY idx_ministry_users_user (user_id),
    CONSTRAINT fk_ministry_users_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id) ON DELETE CASCADE,
    CONSTRAINT fk_ministry_users_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Equipe de mídia: dados complementares, funções e níveis
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media_members (
    user_id       INT UNSIGNED NOT NULL PRIMARY KEY,
    member_status ENUM('ativo','afastado','em_treinamento') NOT NULL DEFAULT 'ativo',
    joined_at     DATE         NULL,
    notes         TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_media_members_status (member_status),
    CONSTRAINT fk_media_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_functions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(80)  NOT NULL,
    description VARCHAR(300) NULL,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_media_functions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_functions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    function_id    INT UNSIGNED NOT NULL,
    level          ENUM('aprendiz','apto','referencia') NOT NULL DEFAULT 'aprendiz',
    trained_at     DATE         NULL,
    is_coordinator TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_functions (user_id, function_id),
    KEY idx_member_functions_function (function_id, level),
    CONSTRAINT fk_member_functions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_functions_function FOREIGN KEY (function_id) REFERENCES media_functions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LGPD
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consents (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL,
    consent_type  ENUM('cadastro','uso_imagem','envio_arquivo') NOT NULL,
    terms_version VARCHAR(20)  NOT NULL,
    accepted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip            VARCHAR(45)  NOT NULL,
    user_agent    VARCHAR(255) NULL,
    revoked_at    DATETIME     NULL,
    revoked_ip    VARCHAR(45)  NULL,
    KEY idx_consents_user (user_id, consent_type),
    CONSTRAINT fk_consents_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_requests (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NULL,
    request_type     ENUM('acesso','correcao','exclusao') NOT NULL,
    details          TEXT         NULL,
    status           ENUM('aberta','concluida','recusada') NOT NULL DEFAULT 'aberta',
    resolved_by      INT UNSIGNED NULL,
    resolved_at      DATETIME     NULL,
    resolution_notes TEXT         NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_data_requests_status (status),
    CONSTRAINT fk_data_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_requests_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Auditoria e limitação de tentativas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(60)  NOT NULL,
    entity      VARCHAR(60)  NOT NULL,
    entity_id   VARCHAR(40)  NULL,
    before_data LONGTEXT     NULL,
    after_data  LONGTEXT     NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity, entity_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_hits (
    id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket   VARCHAR(40)  NOT NULL,
    rate_key VARCHAR(191) NOT NULL,
    hit_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rate_limit (bucket, rate_key, hit_at),
    KEY idx_rate_limit_hit_at (hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Repositório de arquivos (Fase 2)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS folders (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT UNSIGNED NULL,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    visibility  ENUM('restrito','midia','ministerio','membros') NULL,  -- NULL = herda da pasta pai
    ministry_id INT UNSIGNED NULL,                                     -- dono, para visibilidade 'ministerio'
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,                       -- pastas criadas pelo sistema (não apagar)
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_folders_parent (parent_id, sort_order, name),
    KEY idx_folders_ministry (ministry_id),
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE RESTRICT,
    CONSTRAINT fk_folders_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id) ON DELETE SET NULL,
    CONSTRAINT fk_folders_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_uploads (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guest_name     VARCHAR(150) NOT NULL,
    whatsapp       VARCHAR(20)  NULL,
    ministry_id    INT UNSIGNED NULL,
    ministry_name  VARCHAR(120) NULL,
    event_name     VARCHAR(150) NULL,
    description    TEXT         NULL,
    terms_version  VARCHAR(20)  NOT NULL,
    consent_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip             VARCHAR(45)  NOT NULL,
    user_agent     VARCHAR(255) NULL,
    token          CHAR(32)     NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guest_uploads_token (token),
    KEY idx_guest_uploads_ip (ip, created_at),
    CONSTRAINT fk_guest_uploads_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folder_id        INT UNSIGNED NULL,
    driver           VARCHAR(20)  NOT NULL DEFAULT 'local',
    storage_ref      VARCHAR(255) NOT NULL,           -- caminho relativo no driver (nome aleatório)
    display_ref      VARCHAR(255) NULL,               -- versão exibida sem EXIF (imagens JPEG)
    thumb_ref        VARCHAR(255) NULL,
    original_name    VARCHAR(255) NOT NULL,
    extension        VARCHAR(10)  NOT NULL,
    mime             VARCHAR(100) NOT NULL,
    size_bytes       BIGINT UNSIGNED NOT NULL,
    sha256           CHAR(64)     NOT NULL,
    width            INT UNSIGNED NULL,
    height           INT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    category         ENUM('foto','video','audio','arte_final','documento','identidade_visual') NOT NULL DEFAULT 'documento',
    title            VARCHAR(200) NULL,
    description      TEXT         NULL,
    visibility       ENUM('restrito','midia','ministerio','membros') NULL,  -- NULL = herda da pasta
    has_restriction  TINYINT(1)   NOT NULL DEFAULT 0,  -- contém pessoa com restrição de imagem => restrito
    status           ENUM('quarentena','aprovado','rejeitado','lixeira') NOT NULL DEFAULT 'aprovado',
    event_id         INT UNSIGNED NULL,                -- FK criada na Fase 3
    event_name       VARCHAR(150) NULL,
    art_request_id   INT UNSIGNED NULL,                -- FK criada na Fase 4
    uploaded_by      INT UNSIGNED NULL,
    guest_upload_id  INT UNSIGNED NULL,
    upload_ip        VARCHAR(45)  NULL,
    moderated_by     INT UNSIGNED NULL,
    moderated_at     DATETIME     NULL,
    reject_reason    VARCHAR(500) NULL,
    trashed_at       DATETIME     NULL,
    trashed_by       INT UNSIGNED NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_files_folder (folder_id, status),
    KEY idx_files_status (status, created_at),
    KEY idx_files_sha (sha256),
    KEY idx_files_uploader (uploaded_by),
    KEY idx_files_guest (guest_upload_id),
    KEY idx_files_category (category),
    KEY idx_files_event (event_id),
    KEY idx_files_name (original_name),
    CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE RESTRICT,
    CONSTRAINT fk_files_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_files_guest FOREIGN KEY (guest_upload_id) REFERENCES guest_uploads (id) ON DELETE SET NULL,
    CONSTRAINT fk_files_moderator FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_files_trasher FOREIGN KEY (trashed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60) NOT NULL,
    slug       VARCHAR(60) NOT NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS file_tags (
    file_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (file_id, tag_id),
    KEY idx_file_tags_tag (tag_id),
    CONSTRAINT fk_file_tags_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE,
    CONSTRAINT fk_file_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS upload_sessions (
    id              CHAR(32)     NOT NULL PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    guest_upload_id INT UNSIGNED NULL,
    folder_id       INT UNSIGNED NULL,
    original_name   VARCHAR(255) NOT NULL,
    size_bytes      BIGINT UNSIGNED NOT NULL,
    chunk_size      INT UNSIGNED NOT NULL,
    chunks_total    INT UNSIGNED NOT NULL,
    status          ENUM('aberto','montado','duplicado','concluido','cancelado') NOT NULL DEFAULT 'aberto',
    assembled_path  VARCHAR(255) NULL,
    sha256          CHAR(64)     NULL,
    meta            TEXT         NULL,       -- JSON com dados do formulário (descrição, tags, miniatura...)
    file_id         INT UNSIGNED NULL,
    ip              VARCHAR(45)  NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    expires_at      DATETIME     NOT NULL,
    KEY idx_upload_sessions_expires (expires_at),
    KEY idx_upload_sessions_user (user_id),
    KEY idx_upload_sessions_guest (guest_upload_id),
    CONSTRAINT fk_upload_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_upload_sessions_guest FOREIGN KEY (guest_upload_id) REFERENCES guest_uploads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS share_links (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token         CHAR(40)     NOT NULL,
    file_id       INT UNSIGNED NULL,
    folder_id     INT UNSIGNED NULL,
    label         VARCHAR(150) NULL,
    expires_at    DATETIME     NULL,
    max_downloads INT UNSIGNED NULL,
    downloads     INT UNSIGNED NOT NULL DEFAULT 0,
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_share_links_token (token),
    KEY idx_share_links_file (file_id),
    KEY idx_share_links_folder (folder_id),
    CONSTRAINT fk_share_links_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE,
    CONSTRAINT fk_share_links_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE,
    CONSTRAINT fk_share_links_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS download_log (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id       INT UNSIGNED NULL,
    user_id       INT UNSIGNED NULL,
    share_link_id INT UNSIGNED NULL,
    kind          ENUM('download','visualizacao','zip','original') NOT NULL DEFAULT 'download',
    ip            VARCHAR(45)  NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_download_log_file (file_id, created_at),
    KEY idx_download_log_user (user_id),
    CONSTRAINT fk_download_log_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE SET NULL,
    CONSTRAINT fk_download_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_download_log_share FOREIGN KEY (share_link_id) REFERENCES share_links (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Direito de imagem
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS image_restrictions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_name   VARCHAR(150) NOT NULL,
    is_minor      TINYINT(1)   NOT NULL DEFAULT 0,
    guardian_name VARCHAR(150) NULL,
    contact       VARCHAR(100) NULL,
    notes         TEXT         NULL,
    photo_path    VARCHAR(120) NULL,     -- foto de referência (só equipe de mídia)
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_image_restrictions_name (person_name),
    CONSTRAINT fk_image_restrictions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS file_restrictions (
    file_id        INT UNSIGNED NOT NULL,
    restriction_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (file_id, restriction_id),
    CONSTRAINT fk_file_restrictions_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE,
    CONSTRAINT fk_file_restrictions_restriction FOREIGN KEY (restriction_id) REFERENCES image_restrictions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Eventos e escala (Fase 3)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedule_templates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(300) NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedule_templates_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_template_slots (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    function_id INT UNSIGNED NOT NULL,
    quantity    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    notes       VARCHAR(200) NULL,
    UNIQUE KEY uq_template_slots (template_id, function_id),
    CONSTRAINT fk_template_slots_template FOREIGN KEY (template_id) REFERENCES schedule_templates (id) ON DELETE CASCADE,
    CONSTRAINT fk_template_slots_function FOREIGN KEY (function_id) REFERENCES media_functions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_recurrences (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(150) NOT NULL,
    event_type       ENUM('culto','especial','ensaio','reuniao','outro') NOT NULL DEFAULT 'culto',
    frequency        ENUM('semanal','quinzenal','mensal') NOT NULL DEFAULT 'semanal',
    weekday          TINYINT UNSIGNED NOT NULL,          -- 0 = domingo ... 6 = sábado
    week_of_month    TINYINT NULL,                       -- mensal: 1..4 = n-ésimo; -1 = último
    start_time       TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    location         VARCHAR(150) NULL,
    template_id      INT UNSIGNED NULL,
    starts_on        DATE NOT NULL,
    ends_on          DATE NULL,
    active           TINYINT(1) NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recurrences_template FOREIGN KEY (template_id) REFERENCES schedule_templates (id) ON DELETE SET NULL,
    CONSTRAINT fk_recurrences_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(150) NOT NULL,
    event_type    ENUM('culto','especial','ensaio','reuniao','outro') NOT NULL DEFAULT 'culto',
    starts_at     DATETIME NOT NULL,
    ends_at       DATETIME NULL,
    location      VARCHAR(150) NULL,
    description   TEXT NULL,
    ministry_id   INT UNSIGNED NULL,
    recurrence_id INT UNSIGNED NULL,
    status        ENUM('agendado','cancelado','concluido') NOT NULL DEFAULT 'agendado',
    notes         TEXT NULL,                            -- observações da escala (visíveis à equipe)
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_events_starts (starts_at),
    KEY idx_events_status (status, starts_at),
    UNIQUE KEY uq_events_recurrence_day (recurrence_id, starts_at),
    CONSTRAINT fk_events_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id) ON DELETE SET NULL,
    CONSTRAINT fk_events_recurrence FOREIGN KEY (recurrence_id) REFERENCES event_recurrences (id) ON DELETE SET NULL,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_slots (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT UNSIGNED NOT NULL,
    function_id INT UNSIGNED NOT NULL,
    quantity    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    notes       VARCHAR(200) NULL,
    UNIQUE KEY uq_event_slots (event_id, function_id),
    CONSTRAINT fk_event_slots_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_slots_function FOREIGN KEY (function_id) REFERENCES media_functions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    function_id  INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    status       ENUM('pendente','confirmado','recusado') NOT NULL DEFAULT 'pendente',
    assigned_by  INT UNSIGNED NULL,
    responded_at DATETIME NULL,
    note         VARCHAR(300) NULL,                     -- motivo da recusa / observação
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assignments (event_id, function_id, user_id),
    KEY idx_assignments_user (user_id, status),
    KEY idx_assignments_event (event_id),
    CONSTRAINT fk_assignments_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_function FOREIGN KEY (function_id) REFERENCES media_functions (id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_assigner FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS swap_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    from_user_id  INT UNSIGNED NOT NULL,
    to_user_id    INT UNSIGNED NOT NULL,
    reason        VARCHAR(300) NULL,
    status        ENUM('aguardando_membro','aguardando_coordenador','aprovada','recusada_membro','rejeitada','cancelada') NOT NULL DEFAULT 'aguardando_membro',
    decided_by    INT UNSIGNED NULL,
    decided_at    DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_swaps_status (status),
    KEY idx_swaps_to (to_user_id, status),
    CONSTRAINT fk_swaps_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_swaps_from FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_swaps_to FOREIGN KEY (to_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_swaps_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unavailability (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    kind       ENUM('data','recorrente') NOT NULL DEFAULT 'data',
    date_from  DATE NULL,                               -- data única / início do período
    date_to    DATE NULL,                               -- fim do período (data) ou limite (recorrente)
    weekday    TINYINT UNSIGNED NULL,                   -- recorrente: 0 = domingo ... 6 = sábado
    time_from  TIME NULL,                               -- opcional: só parte do dia
    time_to    TIME NULL,
    reason     VARCHAR(200) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_unavailability_user (user_id, kind),
    KEY idx_unavailability_dates (date_from, date_to),
    CONSTRAINT fk_unavailability_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token pessoal para assinar a escala em apps de calendário (ICS sem login)
CREATE TABLE IF NOT EXISTS calendar_tokens (
    user_id    INT UNSIGNED NOT NULL PRIMARY KEY,
    token      CHAR(40) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_calendar_tokens_token (token),
    CONSTRAINT fk_calendar_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pedidos de arte e calendário de comunicação (Fase 4)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS art_checklist_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label      VARCHAR(150) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(150) NOT NULL,
    requester_id    INT UNSIGNED NOT NULL,
    ministry_id     INT UNSIGNED NULL,
    event_id        INT UNSIGNED NULL,
    briefing        TEXT NOT NULL,
    texts           TEXT NULL,                          -- textos que devem constar na arte
    formats         VARCHAR(100) NOT NULL,              -- lista: story,feed,telao,impresso
    publish_on      DATE NOT NULL,                      -- data de publicação desejada
    is_urgent       TINYINT(1) NOT NULL DEFAULT 0,      -- prazo menor que ART_MIN_DAYS
    needs_pastoral  TINYINT(1) NOT NULL DEFAULT 0,
    status          ENUM('recebido','em_producao','revisao_solicitante','aprovacao_midia','aprovacao_pastoral','aprovado','publicado','ajustes','cancelado') NOT NULL DEFAULT 'recebido',
    ajustes_from    VARCHAR(30) NULL,                   -- etapa que pediu ajustes
    designer_id     INT UNSIGNED NULL,
    current_version INT UNSIGNED NOT NULL DEFAULT 0,
    approved_at     DATETIME NULL,
    published_at    DATETIME NULL,
    cancelled_reason VARCHAR(300) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_art_status (status, publish_on),
    KEY idx_art_requester (requester_id),
    KEY idx_art_designer (designer_id),
    KEY idx_art_ministry (ministry_id),
    KEY idx_art_event (event_id),
    CONSTRAINT fk_art_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_art_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id) ON DELETE SET NULL,
    CONSTRAINT fk_art_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_art_designer FOREIGN KEY (designer_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_request_versions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id  INT UNSIGNED NOT NULL,
    version_no  SMALLINT UNSIGNED NOT NULL,
    file_id     INT UNSIGNED NOT NULL,
    notes       VARCHAR(500) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_art_versions (request_id, version_no),
    KEY idx_art_versions_file (file_id),
    CONSTRAINT fk_art_versions_request FOREIGN KEY (request_id) REFERENCES art_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_art_versions_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE RESTRICT,
    CONSTRAINT fk_art_versions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_request_attachments (
    request_id INT UNSIGNED NOT NULL,
    file_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (request_id, file_id),
    CONSTRAINT fk_art_attachments_request FOREIGN KEY (request_id) REFERENCES art_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_art_attachments_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_request_comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NULL,
    user_id    INT UNSIGNED NULL,
    kind       ENUM('comentario','historico') NOT NULL DEFAULT 'comentario',
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_art_comments_request (request_id, created_at),
    CONSTRAINT fk_art_comments_request FOREIGN KEY (request_id) REFERENCES art_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_art_comments_version FOREIGN KEY (version_id) REFERENCES art_request_versions (id) ON DELETE SET NULL,
    CONSTRAINT fk_art_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approvals (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NULL,
    stage      ENUM('solicitante','midia','pastoral') NOT NULL,
    decision   ENUM('aprovado','ajustes') NOT NULL,
    user_id    INT UNSIGNED NULL,
    notes      VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_approvals_request (request_id),
    CONSTRAINT fk_approvals_request FOREIGN KEY (request_id) REFERENCES art_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_approvals_version FOREIGN KEY (version_id) REFERENCES art_request_versions (id) ON DELETE SET NULL,
    CONSTRAINT fk_approvals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_request_checks (
    request_id INT UNSIGNED NOT NULL,
    item_id    INT UNSIGNED NOT NULL,
    checked_by INT UNSIGNED NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, item_id),
    CONSTRAINT fk_art_checks_request FOREIGN KEY (request_id) REFERENCES art_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_art_checks_item FOREIGN KEY (item_id) REFERENCES art_checklist_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_art_checks_user FOREIGN KEY (checked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publications (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(150) NOT NULL,
    channel        ENUM('instagram','facebook','youtube','whatsapp','telao','boletim','site','outro') NOT NULL DEFAULT 'instagram',
    publish_at     DATETIME NOT NULL,
    status         ENUM('planejado','publicado','cancelado') NOT NULL DEFAULT 'planejado',
    request_id     INT UNSIGNED NULL,
    event_id       INT UNSIGNED NULL,
    file_id        INT UNSIGNED NULL,
    responsible_id INT UNSIGNED NULL,
    notes          VARCHAR(500) NULL,
    link           VARCHAR(300) NULL,
    published_at   DATETIME NULL,
    published_by   INT UNSIGNED NULL,
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_publications_at (publish_at, status),
    KEY idx_publications_request (request_id),
    CONSTRAINT fk_publications_request FOREIGN KEY (request_id) REFERENCES art_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_publications_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_publications_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE SET NULL,
    CONSTRAINT fk_publications_responsible FOREIGN KEY (responsible_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_publications_publisher FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_publications_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Recuperação de senha por e-mail (token de uso único, guardado como hash)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id, used_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dados iniciais
-- ---------------------------------------------------------------------
INSERT IGNORE INTO media_functions (name, slug, sort_order) VALUES
    ('Som',               'som',             10),
    ('Projeção',          'projecao',        20),
    ('Câmera',            'camera',          30),
    ('Transmissão',       'transmissao',     40),
    ('Fotografia',        'fotografia',      50),
    ('Design',            'design',          60),
    ('Redes sociais',     'redes-sociais',   70),
    ('Edição de vídeo',   'edicao-video',    80);

-- Administrador inicial. Senha temporária: TrocarAgora!2026 (troca obrigatória no 1º acesso).
-- Altere o e-mail abaixo ANTES de importar, se quiser.
INSERT IGNORE INTO users (id, name, email, password_hash, must_change_password, status)
VALUES (1, 'Administrador', 'admin@admoema.com.br',
        '$2y$12$WZpKe7TWXzatmvk2WdRme.5cnhccrP4C5kupdDWJbsnHLD.jytAwy', 1, 'ativo');

INSERT IGNORE INTO user_roles (user_id, app_code, role) VALUES (1, 'midia', 'admin');
INSERT IGNORE INTO media_members (user_id, member_status) VALUES (1, 'ativo');

-- Pastas iniciais do repositório (raiz)
INSERT IGNORE INTO folders (id, name, visibility, is_system, sort_order) VALUES
    (1, 'Eventos',            'midia',   1, 10),
    (2, 'Ministérios',        'midia',   1, 20),
    (3, 'Identidade Visual',  'midia',   1, 30),
    (4, 'Artes Finais',       'membros', 1, 40);

-- Modelo de escala padrão de culto (edite as quantidades em Escala → Modelos)
INSERT IGNORE INTO schedule_templates (id, name, description) VALUES (1, 'Culto padrão', 'Som, projeção, câmera, transmissão e fotografia');
INSERT IGNORE INTO schedule_template_slots (template_id, function_id, quantity)
SELECT 1, f.id, CASE f.slug WHEN 'camera' THEN 2 ELSE 1 END
  FROM media_functions f WHERE f.slug IN ('som','projecao','camera','transmissao','fotografia');

-- Pasta do sistema para anexos e versões dos pedidos de arte (procurada pelo nome)
INSERT INTO folders (name, visibility, is_system, sort_order, description)
SELECT 'Pedidos de arte', 'midia', 1, 50, 'Anexos de briefing e versões das artes (criada pelo sistema)'
 WHERE NOT EXISTS (SELECT 1 FROM folders WHERE name = 'Pedidos de arte' AND parent_id IS NULL);

-- Checklist de identidade visual (edite em Artes → Checklist)
INSERT INTO art_checklist_items (label, sort_order)
SELECT * FROM (
    SELECT 'Logo da igreja correto e legível' AS label, 10 AS sort_order UNION ALL
    SELECT 'Fontes da identidade visual', 20 UNION ALL
    SELECT 'Cores da paleta oficial', 30 UNION ALL
    SELECT 'Ortografia e textos revisados', 40 UNION ALL
    SELECT 'Data, horário e local conferidos com o evento', 50 UNION ALL
    SELECT 'Dimensões corretas para cada formato', 60 UNION ALL
    SELECT 'Contatos e endereço atualizados', 70
) AS defaults
WHERE NOT EXISTS (SELECT 1 FROM art_checklist_items);
