-- =====================================================================
-- Envio de Mídias ADMoema — esquema do banco (idempotente, compatível com MySQL 5.7)
-- Importe pelo phpMyAdmin. Pode ser executado quantas vezes quiser.
-- Sistema independente: todas as tabelas têm o prefixo em_ e não há ligação com
-- outros sistemas da igreja (usuários, senhas e perfis são próprios).
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS em_users (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(150) NOT NULL,
    email                VARCHAR(191) NOT NULL,
    whatsapp             VARCHAR(20)  NULL,
    photo_path           VARCHAR(120) NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('admin','fotografo','social','membro') NOT NULL DEFAULT 'membro',
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    status               ENUM('pendente','ativo','inativo','anonimizado') NOT NULL DEFAULT 'ativo',
    session_version      INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at        DATETIME     NULL,
    anonymized_at        DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_users_email (email),
    KEY idx_em_users_status (status),
    KEY idx_em_users_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_audit_log (
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

CREATE TABLE IF NOT EXISTS em_rate_limits (
    id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket   VARCHAR(40)  NOT NULL,
    rate_key VARCHAR(191) NOT NULL,
    hit_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rate_limit (bucket, rate_key, hit_at),
    KEY idx_rate_limit_hit_at (hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id, used_at),
    CONSTRAINT fk_em_password_resets_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Segurança do login: código de verificação por e-mail (2ª etapa) e aparelhos confiáveis
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS em_login_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    code_hash  CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used_at    DATETIME     NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_codes_user (user_id, used_at, expires_at),
    CONSTRAINT fk_em_login_codes_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_trusted_devices (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    label      VARCHAR(120) NULL,
    ip         VARCHAR(45)  NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME   NULL,
    UNIQUE KEY uq_em_trusted_devices_token (token_hash),
    KEY idx_trusted_devices_user (user_id, expires_at),
    CONSTRAINT fk_em_trusted_devices_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_user_prefs (
    user_id    INT UNSIGNED NOT NULL,
    pref_key   VARCHAR(60)  NOT NULL,
    value      VARCHAR(255) NULL,
    updated_at DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, pref_key),
    CONSTRAINT fk_em_user_preferences_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Álbuns (uma pasta por culto/evento) e fotos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS em_albums (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_date  DATE         NOT NULL,
    name        VARCHAR(120) NOT NULL,               -- ex.: Culto com a Família
    folder      VARCHAR(160) NOT NULL,               -- pasta física: 2026-11-01 Culto com a Familia
    description VARCHAR(500) NULL,
    cover_photo_id INT UNSIGNED NULL,
    hidden      TINYINT(1)   NOT NULL DEFAULT 0,     -- 1 = só equipe (fotógrafos, social, admin)
    photos_count INT UNSIGNED NOT NULL DEFAULT 0,
    size_bytes  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_albums_folder (folder),
    KEY idx_albums_date (event_date),
    CONSTRAINT fk_em_albums_creator FOREIGN KEY (created_by) REFERENCES em_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_photos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    album_id      INT UNSIGNED NOT NULL,
    parent_id     INT UNSIGNED NULL,                 -- versão editada de outra foto
    file_name     VARCHAR(160) NOT NULL,             -- nome dentro da pasta do álbum (saneado)
    original_name VARCHAR(255) NOT NULL,
    mime          VARCHAR(60)  NOT NULL,
    size_bytes    BIGINT UNSIGNED NOT NULL,
    width         INT UNSIGNED NULL,
    height        INT UNSIGNED NULL,
    sha256        CHAR(64)     NOT NULL,
    taken_at      DATETIME     NULL,                 -- data da câmera (EXIF), se houver
    has_thumb     TINYINT(1)   NOT NULL DEFAULT 0,
    has_display   TINYINT(1)   NOT NULL DEFAULT 0,
    caption       VARCHAR(500) NULL,
    edit_note     VARCHAR(255) NULL,                 -- ajustes aplicados na versão editada
    uploaded_by   INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_photos_album_file (album_id, file_name),
    KEY idx_photos_album (album_id, created_at),
    KEY idx_photos_sha (sha256),
    KEY idx_photos_parent (parent_id),
    CONSTRAINT fk_em_photos_album FOREIGN KEY (album_id) REFERENCES em_albums (id) ON DELETE CASCADE,
    CONSTRAINT fk_em_photos_parent FOREIGN KEY (parent_id) REFERENCES em_photos (id) ON DELETE SET NULL,
    CONSTRAINT fk_em_photos_user FOREIGN KEY (uploaded_by) REFERENCES em_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_uploads (
    id             CHAR(32)     NOT NULL PRIMARY KEY, -- token aleatório
    album_id       INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    original_name  VARCHAR(255) NOT NULL,
    size_bytes     BIGINT UNSIGNED NOT NULL,
    chunk_bytes    INT UNSIGNED NOT NULL,
    chunks_total   INT UNSIGNED NOT NULL,
    chunks_received INT UNSIGNED NOT NULL DEFAULT 0,
    status         ENUM('aberto','concluido','cancelado') NOT NULL DEFAULT 'aberto',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_uploads_status (status, created_at),
    CONSTRAINT fk_em_uploads_album FOREIGN KEY (album_id) REFERENCES em_albums (id) ON DELETE CASCADE,
    CONSTRAINT fk_em_uploads_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_downloads (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id   INT UNSIGNED NULL,
    album_id   INT UNSIGNED NULL,
    user_id    INT UNSIGNED NULL,
    kind       ENUM('original','zip') NOT NULL DEFAULT 'original',
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_downloads_photo (photo_id),
    KEY idx_downloads_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link público temporário de uma foto (a API da Meta precisa baixar a imagem por URL)
CREATE TABLE IF NOT EXISTS em_public_links (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id   INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_public_links_token (token_hash),
    KEY idx_public_links_exp (expires_at),
    CONSTRAINT fk_em_public_links_photo FOREIGN KEY (photo_id) REFERENCES em_photos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Redes sociais: cada pessoa configura a própria conta (token guardado cifrado)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS em_social_accounts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    network        ENUM('instagram','facebook') NOT NULL,
    account_id     VARCHAR(60)  NOT NULL,            -- ID da conta Instagram Business ou da Página do Facebook
    account_name   VARCHAR(150) NULL,                -- @usuario ou nome da página
    token_enc      TEXT         NOT NULL,            -- access token cifrado (AES-256-GCM, chave no .env)
    token_expires_at DATETIME   NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_social_user_network (user_id, network),
    CONSTRAINT fk_em_social_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS em_social_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    network     ENUM('instagram','facebook') NOT NULL,
    caption     TEXT         NULL,
    status      ENUM('publicado','falhou') NOT NULL,
    external_id VARCHAR(80)  NULL,
    permalink   VARCHAR(300) NULL,
    error       VARCHAR(500) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_social_posts_photo (photo_id),
    KEY idx_social_posts_user (user_id, created_at),
    CONSTRAINT fk_em_social_posts_photo FOREIGN KEY (photo_id) REFERENCES em_photos (id) ON DELETE CASCADE,
    CONSTRAINT fk_em_social_posts_user FOREIGN KEY (user_id) REFERENCES em_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dados iniciais: administrador (senha padrão do primeiro acesso: ADMoema2026@1 — troca obrigatória)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO em_users (id, name, email, password_hash, role, must_change_password, status)
VALUES (1, 'Administrador', 'admin@admoema.com.br',
        '$2y$10$gmWAn82ZAmf3ajOnaBsg7ea5v/Lwpp00Zm/whmSRqH.Mca.4tQw9K', 'admin', 1, 'ativo');

-- ---------------------------------------------------------------------
-- Links públicos de compartilhamento (foto ou álbum) com pré-visualização para redes sociais (Open Graph)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS em_share_links (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token      CHAR(22)     NOT NULL,
    photo_id   INT UNSIGNED NULL,
    album_id   INT UNSIGNED NULL,
    expires_at DATETIME     NULL,                     -- NULL = sem validade
    views      INT UNSIGNED NOT NULL DEFAULT 0,
    revoked_at DATETIME     NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_em_share_links_token (token),
    KEY idx_share_links_photo (photo_id),
    KEY idx_share_links_album (album_id),
    CONSTRAINT fk_em_share_links_photo FOREIGN KEY (photo_id) REFERENCES em_photos (id) ON DELETE CASCADE,
    CONSTRAINT fk_em_share_links_album FOREIGN KEY (album_id) REFERENCES em_albums (id) ON DELETE CASCADE,
    CONSTRAINT fk_em_share_links_user FOREIGN KEY (created_by) REFERENCES em_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configurações do administrador (logo / marca d'água, chave da API do Google Drive)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS em_settings (
    setting_key VARCHAR(60)  NOT NULL PRIMARY KEY,
    value       TEXT         NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colunas novas em instalações já existentes (MySQL 5.7 não tem ADD COLUMN IF NOT EXISTS)
SET @c1 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'em_photos' AND COLUMN_NAME = 'source_ref');
SET @s1 = IF(@c1 = 0, 'ALTER TABLE em_photos ADD COLUMN source_ref VARCHAR(120) NULL, ADD KEY idx_em_photos_source (source_ref)', 'SELECT 1');
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;
SET @c2 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'em_photos' AND COLUMN_NAME = 'watermarked');
SET @s2 = IF(@c2 = 0, 'ALTER TABLE em_photos ADD COLUMN watermarked TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;
