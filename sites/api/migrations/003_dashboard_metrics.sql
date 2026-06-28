-- ============================================
-- Dashboard Metrics Storage
-- For financial tracking, analytics, and N-1 comparisons
-- ============================================

-- Monthly aggregated metrics per tenant
CREATE TABLE IF NOT EXISTS metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    metric_type VARCHAR(50) NOT NULL, -- 'revenue', 'quotes', 'invoices', 'visitors', etc.
    period_type ENUM('day', 'week', 'month', 'year') DEFAULT 'month',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    value DECIMAL(15, 2) NOT NULL DEFAULT 0,
    value_count INT DEFAULT 0, -- for counts (nb devis, nb factures)
    metadata JSON NULL, -- extra data (avg, max, etc)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_metric (tenant_id, metric_type, period_type, period_start),
    INDEX idx_tenant_metrics (tenant_id, metric_type, period_start),
    INDEX idx_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenant goals/targets (for weather indicator thresholds)
CREATE TABLE IF NOT EXISTS tenant_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    goal_type VARCHAR(50) NOT NULL, -- 'monthly_revenue', 'quote_conversion'
    target_value DECIMAL(15, 2) NOT NULL,
    warning_threshold DECIMAL(5, 2) DEFAULT 0.90, -- 90% = yellow
    danger_threshold DECIMAL(5, 2) DEFAULT 0.70, -- 70% = red
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_goal (tenant_id, goal_type),
    INDEX idx_tenant_goals (tenant_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dashboard preferences per user
CREATE TABLE IF NOT EXISTS user_dashboard_prefs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    widget_order JSON DEFAULT NULL, -- ['revenue', 'quotes', 'status', 'chantiers', 'analytics']
    hidden_widgets JSON DEFAULT NULL,
    date_range_default ENUM('7d', '30d', '90d', '1y') DEFAULT '30d',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_prefs (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log for dashboard feed
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL, -- 'quote_created', 'invoice_paid', 'client_added'
    entity_type VARCHAR(50) NOT NULL, -- 'quote', 'invoice', 'client'
    entity_id INT NULL,
    description VARCHAR(255),
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_activity (tenant_id, created_at),
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default goals for new tenants
INSERT INTO tenant_goals (tenant_id, goal_type, target_value, warning_threshold, danger_threshold) VALUES
(0, 'monthly_revenue', 10000.00, 0.90, 0.70),
(0, 'quote_conversion_rate', 0.30, 0.80, 0.50)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
