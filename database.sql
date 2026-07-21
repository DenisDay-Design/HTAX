-- Banco de dados de leads da Hinnig Tax & Assets
-- Execute este arquivo no phpMyAdmin/Hostinger após criar o banco hinnig_leads.

CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    empresa VARCHAR(200) NOT NULL,
    cargo VARCHAR(200) NULL,
    email VARCHAR(254) NOT NULL,
    telefone VARCHAR(50) NOT NULL,
    faturamento VARCHAR(100) NULL,
    regime VARCHAR(100) NULL,
    desafio TEXT NULL,
    origem VARCHAR(100) NOT NULL DEFAULT 'site',
    pagina_origem VARCHAR(255) NULL,
    utm_source VARCHAR(100) NULL,
    utm_medium VARCHAR(100) NULL,
    utm_campaign VARCHAR(150) NULL,
    utm_term VARCHAR(150) NULL,
    utm_content VARCHAR(150) NULL,
    consentimento_marketing TINYINT(1) NOT NULL DEFAULT 0,
    consentimento_em DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'novo',
    ip_hash CHAR(64) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leads_email (email),
    INDEX idx_leads_status_data (status, criado_em),
    INDEX idx_leads_consentimento (consentimento_marketing, criado_em),
    INDEX idx_leads_utm_campaign (utm_campaign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
