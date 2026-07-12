ALTER TABLE local_artisans ADD COLUMN auth_token_hash VARCHAR(255) DEFAULT NULL AFTER auth_token_exp;
CREATE INDEX idx_local_artisans_auth_token_hash ON local_artisans(auth_token_hash);

-- NOTE: We cannot bcrypt existing plaintext tokens in pure SQL. Existing sessions
-- remain valid through the legacy auth_token fallback in code and are migrated to
-- hashed storage on the next login. New tokens are stored as password hashes in
-- auth_token_hash and the legacy auth_token column is cleared.
