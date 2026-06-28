-- ============================================
-- WebIArtisan Platform — Client Portal Payments
-- Create paiements_client table for client portal payments
-- ============================================

CREATE TABLE IF NOT EXISTS paiements_client (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    devis_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    type ENUM('acompte','solde','partiel') NOT NULL,
    methode ENUM('stripe','virement','cheque') NOT NULL,
    statut ENUM('en_attente','recu','echec') DEFAULT 'en_attente',
    stripe_session_id VARCHAR(255) NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (devis_id) REFERENCES devis(id) ON DELETE CASCADE,
    
    INDEX idx_paiements_tenant (tenant_id),
    INDEX idx_paiements_devis (devis_id),
    INDEX idx_paiements_statut (statut),
    INDEX idx_paiements_stripe_session (stripe_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
