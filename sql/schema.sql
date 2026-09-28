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
