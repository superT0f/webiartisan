-- ============================================
-- WebIArtisan Platform — Client Portal Features
-- Step-by-step migration for production
-- ============================================

-- ÉTAPE 1: Ajouter les colonnes (une par une pour éviter les erreurs)
-- Exécuter ces commandes une par une

-- 1.1: Ajouter client_token
ALTER TABLE devis ADD COLUMN client_token VARCHAR(64) NULL AFTER status;

-- 1.2: Ajouter client_token_expires_at
ALTER TABLE devis ADD COLUMN client_token_expires_at DATETIME NULL AFTER client_token;

-- 1.3: Ajouter signed_at
ALTER TABLE devis ADD COLUMN signed_at DATETIME NULL AFTER client_token_expires_at;

-- 1.4: Ajouter signed_ip
ALTER TABLE devis ADD COLUMN signed_ip VARCHAR(45) NULL AFTER signed_at;

-- 1.5: Ajouter signed_signature_b64
ALTER TABLE devis ADD COLUMN signed_signature_b64 LONGTEXT NULL AFTER signed_ip;

-- 1.6: Ajouter date_validite
ALTER TABLE devis ADD COLUMN date_validite DATE NULL AFTER created_at;

-- 1.7: Ajouter acompte_pourcentage
ALTER TABLE devis ADD COLUMN acompte_pourcentage DECIMAL(5,2) DEFAULT 0 AFTER tva_rate;

-- 1.8: Ajouter acompte_montant
ALTER TABLE devis ADD COLUMN acompte_montant DECIMAL(10,2) DEFAULT 0 AFTER acompte_pourcentage;

-- ÉTAPE 2: Générer les tokens uniques (après vérification que les colonnes existent)
UPDATE devis SET client_token = CONCAT(
    SUBSTRING(HEX(UUID()), 1, 32), 
    LPAD(id, 8, '0')
) WHERE client_token IS NULL;

-- ÉTAPE 3: Appliquer les contraintes UNIQUE et NOT NULL
ALTER TABLE devis MODIFY COLUMN client_token VARCHAR(64) NOT NULL;
ALTER TABLE devis ADD UNIQUE KEY idx_devis_client_token (client_token);

-- ÉTAPE 4: Ajouter les index pour performance
CREATE INDEX idx_devis_token_expires ON devis(client_token, client_token_expires_at);

-- Vérification finale
SELECT COUNT(*) as total_devis, COUNT(client_token) as devis_with_tokens FROM devis;
