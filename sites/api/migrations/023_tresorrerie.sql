-- ============================================
-- 023 — Trésorerie module
-- Table des dépenses pour le suivi de trésorerie
-- ============================================

CREATE TABLE IF NOT EXISTS depenses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    libelle       VARCHAR(255) NOT NULL,
    categorie     VARCHAR(100) DEFAULT 'Autre',
    montant       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    date_echeance DATE DEFAULT NULL,
    date_paiement DATE DEFAULT NULL,
    status        ENUM('prévu','payé','annulé') DEFAULT 'prévu',
    piece_jointe_url VARCHAR(500) DEFAULT NULL,
    notes         TEXT DEFAULT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_date_echeance (date_echeance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
