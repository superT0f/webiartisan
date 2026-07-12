ALTER TABLE local_artisans ADD COLUMN auth_token_hash VARCHAR(255) DEFAULT NULL AFTER auth_token_exp;
CREATE INDEX idx_local_artisans_auth_token_hash ON local_artisans(auth_token_hash);
