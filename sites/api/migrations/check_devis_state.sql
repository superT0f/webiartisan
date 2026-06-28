-- ============================================
-- WebIArtisan Platform — Client Portal Features
-- Check current state of devis table
-- ============================================

-- Vérifier si les colonnes client portal existent déjà
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'devis' 
  AND COLUMN_NAME IN (
    'client_token', 
    'client_token_expires_at', 
    'signed_at', 
    'signed_ip', 
    'signed_signature_b64',
    'date_validite',
    'acompte_pourcentage',
    'acompte_montant'
  )
ORDER BY ORDINAL_POSITION;

-- Compter les devis et vérifier les tokens
SELECT 
    COUNT(*) as total_devis,
    COUNT(client_token) as devis_with_tokens,
    COUNT(signed_at) as signed_devis,
    COUNT(client_token_expires_at) as tokens_with_expiration
FROM devis;

-- Vérifier s'il y a des tokens en double (devrait retourner 0)
SELECT 
    client_token,
    COUNT(*) as count
FROM devis 
WHERE client_token IS NOT NULL
GROUP BY client_token 
HAVING COUNT(*) > 1;
