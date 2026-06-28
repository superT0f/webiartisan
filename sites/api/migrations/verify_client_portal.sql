-- ============================================
-- WebIArtisan Platform — Client Portal Features
-- Verification script after migrations
-- ============================================

-- Check if columns exist in devis table
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

-- Check if paiements_client table exists
SHOW TABLES LIKE 'paiements_client';

-- Check paiements_client table structure
DESCRIBE paiements_client;

-- Check for existing tokens (should show some data if devis exist)
SELECT 
    COUNT(*) as total_devis,
    COUNT(client_token) as devis_with_tokens,
    COUNT(signed_at) as signed_devis
FROM devis;

-- Sample of generated tokens (first 5)
SELECT 
    id,
    numero,
    client_token,
    client_token_expires_at,
    signed_at,
    signed_ip
FROM devis 
WHERE client_token IS NOT NULL 
ORDER BY id 
LIMIT 5;

-- Check for any duplicate tokens (should return 0 rows)
SELECT 
    client_token,
    COUNT(*) as count
FROM devis 
WHERE client_token IS NOT NULL
GROUP BY client_token 
HAVING COUNT(*) > 1;
