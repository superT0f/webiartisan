-- ============================================
-- Fix: Create activity_log table for dashboard feed
-- Dashboard shows recent activity from this table
-- ============================================

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    description VARCHAR(255),
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_activity (tenant_id, created_at),
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
